<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class InvoiceDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'invoices';
    }

    public function hasData(Event $event): bool
    {
        return $event->invoices()->exists();
    }
}
