<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class TicketDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'ticketing';
    }

    public function hasData(Event $event): bool
    {
        return $event->ticketTypes()->exists() || $event->ticketOrders()->exists() || $event->promoCodes()->exists();
    }
}
