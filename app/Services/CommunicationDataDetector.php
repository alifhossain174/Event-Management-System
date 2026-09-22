<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class CommunicationDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'communications';
    }

    public function hasData(Event $event): bool
    {
        return $event->outboundMessages()->exists() || $event->reminderSchedules()->exists();
    }
}
