<?php

namespace App\Services;

use App\Contracts\BookingConflictChecker;
use App\Models\Booking;
use Carbon\CarbonImmutable;

final class NullBookingConflictChecker implements BookingConflictChecker
{
    public function conflicts(
        Booking $booking,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $venuePreference,
    ): array {
        return [];
    }
}
