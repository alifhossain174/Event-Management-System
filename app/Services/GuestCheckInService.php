<?php

namespace App\Services;

use App\Models\Event;
use App\Models\GuestCheckIn;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GuestCheckInService
{
    public function __construct(private readonly AuditService $audit) {}

    public function lookup(Event $event, string $token): GuestCheckInResult
    {
        $invitation = $this->resolve($event, $token);
        $checkIn = GuestCheckIn::query()->with('operator')->where('guest_id', $invitation->guest_id)->first();

        return new GuestCheckInResult($invitation->load(['guest.rsvp', 'guest.seatAssignment', 'guest.groupMembership.group']), $checkIn, $checkIn !== null);
    }

    public function checkIn(Event $event, string $token, User $actor, ?int $partySize = null, ?string $notes = null): GuestCheckInResult
    {
        return DB::transaction(function () use ($event, $token, $actor, $partySize, $notes) {
            $invitation = Invitation::query()
                ->where('event_id', $event->getKey())
                ->where('check_in_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();
            if (! $invitation || ! $invitation->isUsable()) {
                throw ValidationException::withMessages(['token' => 'This check-in token is invalid, expired, revoked, or belongs to another Event.']);
            }
            $invitation->load('guest');
            if ($invitation->guest->archived_at) {
                throw ValidationException::withMessages(['token' => 'This check-in token is no longer active.']);
            }
            $existing = GuestCheckIn::query()->with('operator')->where('guest_id', $invitation->guest_id)->first();
            if ($existing) {
                return new GuestCheckInResult($invitation->load(['guest.rsvp', 'guest.seatAssignment', 'guest.groupMembership.group']), $existing, true);
            }

            $maximum = max(1, (int) $invitation->guest->confirmed_party_size ?: (int) $invitation->guest->invited_party_size);
            $partySize ??= $maximum;
            if ($partySize < 1 || $partySize > $maximum) {
                throw ValidationException::withMessages(['party_size' => "Party size must be between 1 and {$maximum}."]);
            }

            $checkIn = GuestCheckIn::query()->create([
                'event_id' => $event->getKey(), 'guest_id' => $invitation->guest_id,
                'invitation_id' => $invitation->getKey(), 'party_size' => $partySize,
                'method' => 'qr', 'checked_in_at' => now(), 'checked_in_by_user_id' => $actor->getKey(),
                'notes' => $notes, 'created_at' => now(),
            ]);
            $this->audit->record('guest.checked_in', $checkIn, [], [
                'event_id' => $event->getKey(), 'guest_id' => $invitation->guest_id, 'party_size' => $partySize,
            ], $actor);

            return new GuestCheckInResult($invitation->load(['guest.rsvp', 'guest.seatAssignment', 'guest.groupMembership.group']), $checkIn->load('operator'), false);
        }, 3);
    }

    private function resolve(Event $event, string $token): Invitation
    {
        $invitation = Invitation::query()
            ->where('event_id', $event->getKey())
            ->where('check_in_token_hash', hash('sha256', $token))
            ->first();
        if (! $invitation || ! $invitation->isUsable() || $invitation->guest()->whereNotNull('archived_at')->exists()) {
            throw ValidationException::withMessages(['token' => 'This check-in token is invalid, expired, revoked, or belongs to another Event.']);
        }

        return $invitation;
    }
}
