<?php

declare(strict_types=1);

namespace App\Application\Reporting\Commands;

use App\Application\Reporting\ReportScheduler;
use Illuminate\Console\Command;

class SendScheduledReportsCommand extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Process and send all scheduled reports that are due';

    public function handle(ReportScheduler $scheduler): int
    {
        $stats = $scheduler->processDue();
        $this->info("Processed: {$stats['processed']}, Sent: {$stats['sent']}, Errors: {$stats['errors']}");

        return self::SUCCESS;
    }
}
