<?php

namespace App\Services;

use App\Contracts\BookingConflictChecker;
use App\Models\Booking;
use App\Models\EventVenueAllocation;
use App\Models\Venue;
use Carbon\CarbonImmutable;

final class VenueBookingConflictChecker implements BookingConflictChecker
{
    public function conflicts(Booking $booking, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $venuePreference): array
    {
        if (blank($venuePreference)) {
            return [];
        }
        $venue = Venue::query()->where('status', 'active')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($venuePreference))])->first();
        if (! $venue) {
            return [];
        }
        $conflicts = EventVenueAllocation::query()
            ->where('venue_id', $venue->getKey())
            ->where('is_exclusive', true)
            ->whereIn('status', EventVenueAllocation::ACTIVE_STATUSES)
            ->whereHas('event', fn ($query) => $query->where('status', '!=', 'cancelled')
                ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt))
            ->with('event:id,reference_number,name')
            ->limit(10)->get();

        return $conflicts->map(fn ($allocation) => "{$venue->name} is allocated to {$allocation->event->reference_number} ({$allocation->event->name}).")->all();
    }
}
