<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Reporting\ReportScheduler;
use Illuminate\Console\Command;

final class SendScheduledReportsCommand extends Command
{
    protected $signature = 'reports:send-scheduled';

    protected $description = 'Process and send all scheduled reports that are due';

    public function handle(ReportScheduler $scheduler): int
    {
        $stats = $scheduler->processDue();
        $this->info("Processed: {$stats['processed']}, Generated: {$stats['generated']}, Emails: {$stats['sent']}, Errors: {$stats['errors']}");

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
