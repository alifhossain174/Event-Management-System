<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\GuestGroupMember;
use App\Models\GuestNote;
use App\Models\Rsvp;
use App\Models\SeatAssignment;
use App\Models\StatusHistory;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GuestService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(Event $event, array $data, User $actor): Guest
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $guest = Guest::query()->create($this->guestAttributes($event, $data) + [
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->syncGroup($guest, Arr::get($data, 'guest_group_id'), Arr::get($data, 'relationship_label'), $actor);
            $this->audit->record('guest.created', $guest, [], $this->snapshot($guest), $actor);

            return $guest->refresh();
        }, 3);
    }

    public function update(Guest $guest, array $data, User $actor): Guest
    {
        return DB::transaction(function () use ($guest, $data, $actor) {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            $before = $this->snapshot($guest);
            $guest->update($this->guestAttributes($guest->event, $data) + ['updated_by_user_id' => $actor->getKey()]);
            $this->syncGroup($guest, Arr::get($data, 'guest_group_id'), Arr::get($data, 'relationship_label'), $actor);
            $this->audit->record('guest.updated', $guest, $before, $this->snapshot($guest), $actor);

            return $guest->refresh();
        }, 3);
    }

    public function archive(Guest $guest, User $actor, string $reason): Guest
    {
        return DB::transaction(function () use ($guest, $actor, $reason) {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            if ($guest->archived_at) {
                throw ValidationException::withMessages(['action' => 'Guest is already archived.']);
            }
            $guest->update([
                'archived_at' => now(), 'archived_by_user_id' => $actor->getKey(),
                'archive_reason' => $reason, 'invitation_status' => 'revoked',
            ]);
            $guest->invitations()->where('status', '!=', 'revoked')->get()->each(function ($invitation) use ($actor, $reason) {
                $from = $invitation->status;
                $invitation->update(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_id' => $actor->getKey(), 'revocation_reason' => "Guest archived: {$reason}"]);
                StatusHistory::query()->create([
                    'subject_type' => $invitation->getMorphClass(), 'subject_id' => $invitation->getKey(),
                    'from_status' => $from, 'to_status' => 'revoked', 'actor_type' => 'user',
                    'actor_user_id' => $actor->getKey(), 'changed_at' => now(),
                    'reason' => "Guest archived: {$reason}",
                ]);
            });
            $this->audit->record('guest.archived', $guest, [], ['reason' => $reason], $actor);

            return $guest->refresh();
        }, 3);
    }

    public function reactivate(Guest $guest, User $actor, ?string $reason = null): Guest
    {
        return DB::transaction(function () use ($guest, $actor, $reason) {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            if (! $guest->archived_at) {
                throw ValidationException::withMessages(['action' => 'Guest is already active.']);
            }
            $guest->update(['archived_at' => null, 'archived_by_user_id' => null, 'archive_reason' => null]);
            $this->audit->record('guest.reactivated', $guest, [], ['reason' => $reason], $actor);

            return $guest->refresh();
        });
    }

    public function createGroup(Event $event, array $data, User $actor): GuestGroup
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $group = GuestGroup::query()->create([
                'event_id' => $event->getKey(), 'name' => trim($data['name']), 'type' => $data['type'],
                'description' => $data['description'] ?? null, 'is_vip' => (bool) ($data['is_vip'] ?? false),
                'created_by_user_id' => $actor->getKey(), 'updated_by_user_id' => $actor->getKey(),
            ]);
            $this->audit->record('guest_group.created', $group, [], ['event_id' => $event->getKey(), 'name' => $group->name], $actor);

            return $group;
        });
    }

    public function saveRsvp(Guest $guest, array $data, User $actor): Rsvp
    {
        return DB::transaction(function () use ($guest, $data, $actor) {
            $guest = Guest::query()->lockForUpdate()->findOrFail($guest->getKey());
            $attending = in_array($data['status'], ['accepted', 'tentative'], true) ? (int) $data['attending_count'] : 0;
            $plusOnes = in_array($data['status'], ['accepted', 'tentative'], true) ? (int) ($data['plus_one_count'] ?? 0) : 0;
            $maximumPartySize = $guest->invited_party_size + $guest->plus_one_limit;
            if ($plusOnes > $guest->plus_one_limit || $attending > $maximumPartySize || $plusOnes > max(0, $attending - 1)) {
                throw ValidationException::withMessages(['attending_count' => 'Attendance exceeds this Guest’s plus-one policy.']);
            }

            $rsvp = Rsvp::query()->where('guest_id', $guest->getKey())->lockForUpdate()->first();
            $from = $rsvp?->status ?? 'pending';
            $attributes = [
                'event_id' => $guest->event_id, 'guest_id' => $guest->getKey(),
                'invitation_id' => $guest->activeInvitation?->getKey(), 'status' => $data['status'],
                'attending_count' => $attending, 'plus_one_count' => $plusOnes,
                'response_source' => $data['response_source'] ?? 'manager',
                'response_note' => $data['response_note'] ?? null, 'responded_at' => now(),
                'recorded_by_user_id' => $actor->getKey(),
            ];
            $rsvp ? $rsvp->update($attributes) : $rsvp = Rsvp::query()->create($attributes);
            $guest->update(['rsvp_status' => $data['status'], 'confirmed_party_size' => $attending, 'updated_by_user_id' => $actor->getKey()]);
            if ($from !== $data['status']) {
                StatusHistory::query()->create([
                    'subject_type' => $rsvp->getMorphClass(), 'subject_id' => $rsvp->getKey(),
                    'from_status' => $from, 'to_status' => $data['status'], 'actor_type' => 'user',
                    'actor_user_id' => $actor->getKey(), 'changed_at' => now(),
                    'reason' => $data['response_note'] ?? null,
                ]);
            }
            $this->audit->record('guest.rsvp_recorded', $rsvp, ['status' => $from], ['status' => $data['status'], 'attending_count' => $attending], $actor);

            return $rsvp->refresh();
        }, 3);
    }

    public function assignSeat(Guest $guest, array $data, User $actor): SeatAssignment
    {
        return DB::transaction(function () use ($guest, $data, $actor) {
            $guest = Guest::query()->lockForUpdate()->with('groupMembership')->findOrFail($guest->getKey());
            $groupId = $data['guest_group_id'] ?? $guest->groupMembership?->guest_group_id;
            if ($groupId && ! GuestGroup::query()->whereKey($groupId)->where('event_id', $guest->event_id)->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['guest_group_id' => 'The selected group does not belong to this Event.']);
            }
            $seat = SeatAssignment::query()->updateOrCreate(['guest_id' => $guest->getKey()], [
                'event_id' => $guest->event_id, 'guest_group_id' => $groupId,
                'table_label' => trim($data['table_label']), 'seat_label' => trim($data['seat_label']),
                'notes' => $data['notes'] ?? null, 'assigned_by_user_id' => $actor->getKey(), 'assigned_at' => now(),
            ]);
            $this->audit->record('guest.seat_assigned', $seat, [], ['table_label' => $seat->table_label, 'seat_label' => $seat->seat_label], $actor);

            return $seat->refresh();
        }, 3);
    }

    public function addNote(Guest $guest, array $data, User $actor): GuestNote
    {
        $note = GuestNote::query()->create([
            'event_id' => $guest->event_id, 'guest_id' => $guest->getKey(),
            'visibility' => $data['visibility'], 'body' => $data['body'], 'author_user_id' => $actor->getKey(),
        ]);
        $this->audit->record('guest.note_added', $guest, [], ['note_id' => $note->getKey(), 'visibility' => $note->visibility], $actor);

        return $note;
    }

    private function guestAttributes(Event $event, array $data): array
    {
        $first = trim($data['first_name']);
        $last = trim((string) ($data['last_name'] ?? ''));
        $display = trim("{$first} {$last}");
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        $phone = filled($data['phone'] ?? null) ? trim($data['phone']) : null;

        return [
            'event_id' => $event->getKey(), 'external_reference' => $data['external_reference'] ?? null,
            'first_name' => $first, 'last_name' => $last ?: null, 'display_name' => $display,
            'normalized_name' => Str::lower($display), 'email' => $email, 'normalized_email' => $email,
            'phone' => $phone, 'normalized_phone' => $phone ? preg_replace('/\D+/', '', $phone) : null,
            'is_vip' => (bool) ($data['is_vip'] ?? false), 'plus_one_policy' => $data['plus_one_policy'],
            'plus_one_limit' => $data['plus_one_policy'] === 'none' ? 0 : (int) $data['plus_one_limit'],
            'invited_party_size' => (int) $data['invited_party_size'], 'source' => $data['source'] ?? 'manual',
        ];
    }

    private function syncGroup(Guest $guest, mixed $groupId, ?string $relationship, User $actor): void
    {
        GuestGroupMember::query()->where('guest_id', $guest->getKey())->delete();
        if (! $groupId) {
            return;
        }
        if (! GuestGroup::query()->whereKey($groupId)->where('event_id', $guest->event_id)->whereNull('archived_at')->exists()) {
            throw ValidationException::withMessages(['guest_group_id' => 'The selected group does not belong to this Event.']);
        }
        GuestGroupMember::query()->create([
            'event_id' => $guest->event_id, 'guest_group_id' => $groupId, 'guest_id' => $guest->getKey(),
            'relationship_label' => $relationship, 'added_by_user_id' => $actor->getKey(), 'added_at' => now(),
        ]);
    }

    private function snapshot(Guest $guest): array
    {
        return Arr::only($guest->toArray(), ['event_id', 'display_name', 'email', 'phone', 'is_vip', 'plus_one_policy', 'plus_one_limit', 'invited_party_size']);
    }
}
