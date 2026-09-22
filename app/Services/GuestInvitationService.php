<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GuestInvitationService
{
    public function __construct(private readonly AuditService $audit) {}

    public function issue(Guest $guest, array $data, User $actor): Invitation
    {
        return DB::transaction(function () use ($guest, $data, $actor) {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            if ($guest->archived_at) {
                throw ValidationException::withMessages(['guest' => 'Archived Guests cannot receive invitations.']);
            }

            $guest->invitations()->where('status', '!=', 'revoked')->get()->each(function (Invitation $invitation) use ($actor) {
                $this->revokeLocked($invitation, $actor, 'Replaced by a newly issued invitation.');
            });

            do {
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $hash = hash('sha256', $token);
            } while (Invitation::query()->where('check_in_token_hash', $hash)->exists());

            $invitation = Invitation::query()->create([
                'event_id' => $guest->event_id, 'guest_id' => $guest->getKey(), 'status' => 'issued',
                'delivery_channel' => $data['delivery_channel'] ?? 'manual',
                'check_in_token_hash' => $hash, 'check_in_token_encrypted' => Crypt::encryptString($token),
                'issued_at' => now(), 'issued_by_user_id' => $actor->getKey(),
                'sent_at' => ($data['mark_sent'] ?? false) ? now() : null,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            if ($invitation->sent_at) {
                $invitation->update(['status' => 'sent']);
            }
            $guest->update(['invitation_status' => $invitation->status, 'updated_by_user_id' => $actor->getKey()]);
            StatusHistory::query()->create([
                'subject_type' => $invitation->getMorphClass(), 'subject_id' => $invitation->getKey(),
                'from_status' => 'none', 'to_status' => $invitation->status, 'actor_type' => 'user',
                'actor_user_id' => $actor->getKey(), 'changed_at' => now(),
            ]);
            $this->audit->record('guest.invitation_issued', $invitation, [], [
                'event_id' => $guest->event_id, 'guest_id' => $guest->getKey(),
                'delivery_channel' => $invitation->delivery_channel, 'expires_at' => $invitation->expires_at?->toIso8601String(),
            ], $actor);

            return $invitation->refresh();
        }, 3);
    }

    public function revoke(Invitation $invitation, User $actor, string $reason): Invitation
    {
        return DB::transaction(function () use ($invitation, $actor, $reason) {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());
            if ($invitation->status === 'revoked') {
                throw ValidationException::withMessages(['reason' => 'Invitation is already revoked.']);
            }

            return $this->revokeLocked($invitation, $actor, $reason);
        }, 3);
    }

    public function checkInUrl(Invitation $invitation): string
    {
        return route('events.guests.check-in.lookup', ['event' => $invitation->event_id, 'token' => $invitation->plainToken()]);
    }

    private function revokeLocked(Invitation $invitation, User $actor, string $reason): Invitation
    {
        $from = $invitation->status;
        $invitation->update([
            'status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_id' => $actor->getKey(),
            'revocation_reason' => $reason,
        ]);
        $invitation->guest()->update(['invitation_status' => 'revoked', 'updated_by_user_id' => $actor->getKey()]);
        StatusHistory::query()->create([
            'subject_type' => $invitation->getMorphClass(), 'subject_id' => $invitation->getKey(),
            'from_status' => $from, 'to_status' => 'revoked', 'actor_type' => 'user',
            'actor_user_id' => $actor->getKey(), 'changed_at' => now(), 'reason' => $reason,
        ]);
        $this->audit->record('guest.invitation_revoked', $invitation, ['status' => $from], ['status' => 'revoked', 'reason' => Str::limit($reason, 500)], $actor);

        return $invitation->refresh();
    }
}
