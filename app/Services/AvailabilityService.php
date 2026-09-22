<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventVenueAllocation;
use App\Models\EventVenueAllocationStatusHistory;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSpace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AvailabilityService
{
    public function __construct(private readonly AuditService $audit, private readonly BranchScope $branches) {}

    /** @return Collection<int, EventVenueAllocation> */
    public function conflicts(Event $event, Venue $venue, ?VenueSpace $space, bool $exclusive = true): Collection
    {
        return EventVenueAllocation::query()
            ->with(['event', 'venue', 'space'])
            ->where('venue_id', $venue->getKey())
            ->whereIn('status', EventVenueAllocation::ACTIVE_STATUSES)
            ->whereHas('event', fn ($query) => $query
                ->where('status', '!=', 'cancelled')
                ->where('starts_at', '<', $event->ends_at)
                ->where('ends_at', '>', $event->starts_at))
            ->where(function ($query) use ($space) {
                if ($space === null) {
                    return $query;
                }

                return $query->whereNull('venue_space_id')->orWhere('venue_space_id', $space->getKey());
            })
            ->where(function ($query) use ($exclusive) {
                if ($exclusive) {
                    return $query;
                }

                return $query->where('is_exclusive', true);
            })
            ->lockForUpdate()
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function allocate(Event $event, array $data, User $actor): EventVenueAllocation
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $venue = Venue::query()->lockForUpdate()->findOrFail($data['venue_id']);
            $space = isset($data['venue_space_id'])
                ? VenueSpace::query()->lockForUpdate()->findOrFail($data['venue_space_id'])
                : null;

            if (! $this->branches->permits($actor, $venue->branch_id)) {
                throw new AuthorizationException('You cannot allocate a venue outside your branch access.');
            }
            if ($venue->status !== 'active' || ($space && ($space->venue_id !== $venue->getKey() || ! $space->is_active))) {
                throw ValidationException::withMessages(['venue_id' => 'Select an active venue and a space that belongs to it.']);
            }

            $exclusive = (bool) ($data['is_exclusive'] ?? true);
            $conflicts = $this->conflicts($lockedEvent, $venue, $space, $exclusive);
            $override = (bool) ($data['override_conflict'] ?? false);
            $overrideReason = $data['override_reason'] ?? null;

            if ($conflicts->isNotEmpty() && ! $override) {
                throw ValidationException::withMessages([
                    'override_conflict' => 'This allocation overlaps an exclusive allocation. An authorized override is required.',
                ]);
            }
            if ($conflicts->isNotEmpty() && ! $actor->hasPermission('venues.override-conflicts')) {
                throw new AuthorizationException('You are not authorized to override venue conflicts.');
            }
            if ($conflicts->isNotEmpty() && blank($overrideReason)) {
                throw ValidationException::withMessages(['override_reason' => 'Explain why this venue conflict is being overridden.']);
            }

            $capacity = $space?->capacity ?? $venue->capacity;
            $capacityWarning = $capacity !== null && $lockedEvent->expected_guest_count !== null
                && $lockedEvent->expected_guest_count > $capacity;

            $allocation = EventVenueAllocation::query()->create([
                'event_id' => $lockedEvent->getKey(),
                'venue_id' => $venue->getKey(),
                'venue_space_id' => $space?->getKey(),
                'status' => $data['status'] ?? 'planned',
                'is_exclusive' => $exclusive,
                'quoted_price' => $data['quoted_price'] ?? null,
                'currency_code' => $data['currency_code'] ?? null,
                'rate_type_snapshot' => $data['rate_type_snapshot'] ?? null,
                'capacity_snapshot' => $capacity,
                'capacity_warning' => $capacityWarning,
                'conflict_override' => $conflicts->isNotEmpty(),
                'conflict_override_reason' => $conflicts->isNotEmpty() ? $overrideReason : null,
                'conflict_override_by_user_id' => $conflicts->isNotEmpty() ? $actor->getKey() : null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
            ]);

            EventVenueAllocationStatusHistory::query()->create([
                'event_venue_allocation_id' => $allocation->getKey(),
                'from_status' => null,
                'to_status' => $allocation->status,
                'actor_user_id' => $actor->getKey(),
                'reason' => 'Venue allocated to event',
                'changed_at' => now(),
            ]);
            $this->audit->record('event.venue_allocated', $allocation, [], [
                'event_id' => $event->getKey(), 'venue_id' => $venue->getKey(),
                'venue_space_id' => $space?->getKey(), 'status' => $allocation->status,
                'capacity_warning' => $capacityWarning, 'conflict_override' => $conflicts->isNotEmpty(),
                'override_reason' => $conflicts->isNotEmpty() ? $overrideReason : null,
            ], $actor);

            return $allocation->load(['venue', 'space', 'statusHistory']);
        }, 3);
    }

    public function cancel(EventVenueAllocation $allocation, User $actor, string $reason): EventVenueAllocation
    {
        return DB::transaction(function () use ($allocation, $actor, $reason) {
            $locked = EventVenueAllocation::query()->lockForUpdate()->findOrFail($allocation->getKey());
            if ($locked->status === 'cancelled') {
                return $locked;
            }
            $from = $locked->status;
            $locked->update([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(), 'cancellation_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
            ]);
            EventVenueAllocationStatusHistory::query()->create([
                'event_venue_allocation_id' => $locked->getKey(), 'from_status' => $from,
                'to_status' => 'cancelled', 'actor_user_id' => $actor->getKey(),
                'reason' => $reason, 'changed_at' => now(),
            ]);
            $this->audit->record('event.venue_allocation_cancelled', $locked, ['status' => $from], [
                'status' => 'cancelled', 'reason' => $reason,
            ], $actor);

            return $locked->fresh();
        });
    }
}
