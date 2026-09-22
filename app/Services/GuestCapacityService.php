<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventVenueAllocation;

final class GuestCapacityService
{
    /** @return array{confirmed:int,capacity:?int,warning:bool,source:?string} */
    public function summary(Event $event): array
    {
        $confirmed = (int) $event->guests()->whereNull('archived_at')->sum('confirmed_party_size');
        $allocation = $event->venueAllocations()
            ->whereIn('status', EventVenueAllocation::ACTIVE_STATUSES)
            ->with(['venue', 'space'])
            ->latest('id')
            ->first();
        $capacity = $allocation?->capacity_snapshot ?? $allocation?->space?->capacity ?? $allocation?->venue?->capacity;

        return [
            'confirmed' => $confirmed,
            'capacity' => $capacity === null ? null : (int) $capacity,
            'warning' => $capacity !== null && $confirmed > (int) $capacity,
            'source' => $allocation?->space?->name ?? $allocation?->venue?->name,
        ];
    }
}
