<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

final class DispatchDueReminders extends Command
{
    protected $signature = 'notifications:send-due-reminders';

    protected $description = 'Create idempotent in-app Event and payment-due reminders and process scheduled reminders';

    public function handle(ReminderService $reminders): int
    {
        $counts = $reminders->dispatchDue();
        $this->info("Processed {$counts['scheduled']} scheduled, {$counts['events']} Event, and {$counts['payments']} payment reminders; {$counts['failed']} failed.");

        return self::SUCCESS;
    }
}
