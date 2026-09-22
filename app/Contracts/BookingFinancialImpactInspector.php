<?php

namespace App\Contracts;

use App\Models\Booking;

interface BookingFinancialImpactInspector
{
    /** @return list<string> */
    public function cancellationFlags(Booking $booking): array;
}
