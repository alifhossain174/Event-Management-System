<?php

namespace App\Contracts;

use App\Models\Booking;
use Carbon\CarbonImmutable;

interface BookingConflictChecker
{
    /** @return list<string> */
    public function conflicts(
        Booking $booking,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $venuePreference,
    ): array;
}
