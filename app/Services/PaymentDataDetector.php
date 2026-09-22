<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class PaymentDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'payments';
    }

    public function hasData(Event $event): bool
    {
        return $event->payments()->exists()
            || $event->paymentSchedules()->exists()
            || $event->refunds()->exists();
    }
}
