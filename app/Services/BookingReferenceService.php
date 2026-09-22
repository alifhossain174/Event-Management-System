<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Str;

final class BookingReferenceService
{
    public function next(): string
    {
        do {
            $reference = 'BKG-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (Booking::query()->where('reference_number', $reference)->exists());

        return $reference;
    }
}
