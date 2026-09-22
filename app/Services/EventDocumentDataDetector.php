<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class EventDocumentDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'documents';
    }

    public function hasData(Event $event): bool
    {
        return $event->documentLinks()->exists();
    }
}
