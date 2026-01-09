<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PruneOutboxCommand - Storage Health Automation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RETENTION POLICY: Removes events older than X days.                    ║
 * ║  NON-BLOCKING: Deletes in small chunks to avoid locking the table.      ║
 * ║  MAINTENANCE: Prevents "Storage Time Bomb" (Table Bloat).               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PruneOutboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:prune 
                            {--days=7 : Retention period in days for published events}
                            {--failed-days=30 : Retention period in days for failed events}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old events from the outbox table to prevent table bloat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $failedDays = (int) $this->option('failed-days');

        $this->info("Pruning PUBLISHED events older than {$days} days...");
        $this->pruneByStatus('PUBLISHED', $days);

        $this->info("Pruning FAILED events older than {$failedDays} days...");
        $this->pruneByStatus('FAILED', $failedDays);

        $this->info("Outbox pruning completed successfully.");

        return Command::SUCCESS;
    }

    private function pruneByStatus(string $status, int $days): void
    {
        $cutoffDate = now()->subDays($days)->toDateTimeString();
        $totalDeleted = 0;
        $batchSize = 1000; // Small batch to avoid long locks

        do {
            // Delete in chunks
            $rowsAffected = DB::table('domain_outbox_events')
                ->where('status', $status)
                ->where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $rowsAffected;

            if ($rowsAffected > 0) {
                // Sleep to let other transactions drift through (High Load Env)
                usleep(100000); // 100ms pause
                $this->output->write('.');
            }

        } while ($rowsAffected > 0);

        if ($totalDeleted > 0) {
            $this->newLine();
            $this->info("Deleted {$totalDeleted} {$status} events.");
            Log::info("Pruned {$totalDeleted} {$status} outbox events older than {$days} days.");
        } else {
            $this->info("No {$status} events found to prune.");
        }
    }
}
