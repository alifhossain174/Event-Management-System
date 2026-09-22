<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class GuestDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'guests';
    }

    public function hasData(Event $event): bool
    {
        return $event->guests()->exists() || $event->guestGroups()->exists();
    }
}
