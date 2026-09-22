<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class TaskDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'tasks';
    }

    public function hasData(Event $event): bool
    {
        return $event->tasks()->exists();
    }
}
