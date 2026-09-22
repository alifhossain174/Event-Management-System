<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class StaffAssignmentDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'staff';
    }

    public function hasData(Event $event): bool
    {
        return $event->staffAssignments()->exists();
    }
}
