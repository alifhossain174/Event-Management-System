<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class VenueAllocationDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'venue';
    }

    public function hasData(Event $event): bool
    {
        return $event->venueAllocations()->exists();
    }
}
