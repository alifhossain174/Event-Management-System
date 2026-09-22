<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

final class CheckCommunicationExpiries extends Command
{
    protected $signature = 'notifications:check-expiries {--days=30 : Look-ahead window in days}';

    protected $description = 'Create idempotent in-app alerts for expiring vendor contracts';

    public function handle(ReminderService $reminders): int
    {
        $days = max(0, min(365, (int) $this->option('days')));
        $count = $reminders->checkContractExpiries($days);
        $this->info("Checked contract expiries and considered {$count} matching records.");

        return self::SUCCESS;
    }
}
