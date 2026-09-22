<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class VendorAssignmentDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'vendors';
    }

    public function hasData(Event $event): bool
    {
        return $event->vendorAssignments()->exists();
    }
}
