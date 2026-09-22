<?php

namespace App\Services;

use App\Contracts\EventModuleDataDetector;
use App\Models\Event;

final class BudgetDataDetector implements EventModuleDataDetector
{
    public function moduleKey(): string
    {
        return 'budget';
    }

    public function hasData(Event $event): bool
    {
        return $event->budget()->exists()
            || $event->budgetLines()->exists()
            || $event->incomes()->exists()
            || $event->expenses()->exists();
    }
}
