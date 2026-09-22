<?php

namespace App\Services;

use App\Contracts\BookingFinancialImpactInspector;
use App\Models\Booking;

final class NullBookingFinancialImpactInspector implements BookingFinancialImpactInspector
{
    public function cancellationFlags(Booking $booking): array
    {
        return [];
    }
}
