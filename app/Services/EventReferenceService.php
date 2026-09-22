<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;

final class EventReferenceService
{
    public function next(): string
    {
        do {
            $reference = 'EVT-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (Event::query()->where('reference_number', $reference)->exists());

        return $reference;
    }
}
