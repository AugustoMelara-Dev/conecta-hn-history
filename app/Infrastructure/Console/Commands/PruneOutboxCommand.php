<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PruneOutboxCommand - Level 42 True (Lifecycle Management)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RETENTION: 7 days for COMPLETED, 30 days for FAILED                     ║
 * ║  PERFORMANCE: Chunked deletion to prevent table locks                     ║
 * ║  DETERMINISM: Ordered by ID for replication safety                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class PruneOutboxCommand extends Command
{
    protected $signature = 'outbox:prune 
                            {--days=7 : Retention period in days for completed events}
                            {--failed-days=30 : Retention period in days for failed events}';

    protected $description = 'Prune old events from the outbox table to prevent table bloat';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $failedDays = (int) $this->option('failed-days');

        $this->info("Pruning COMPLETED events older than {$days} days...");
        $this->pruneByStatus(OutboxMessage::STATUS_COMPLETED, $days);

        $this->info("Pruning FAILED events older than {$failedDays} days...");
        $this->pruneByStatus(OutboxMessage::STATUS_FAILED, $failedDays);

        $this->info('Outbox pruning completed successfully.');

        return Command::SUCCESS;
    }

    private function pruneByStatus(string $status, int $days): void
    {
        $cutoffDate = now()->subDays($days)->toDateTimeString();
        $totalDeleted = 0;
        $batchSize = 1000;

        // Uses the canonical outbox table instead of legacy event table names.
        do {
            $rowsAffected = DB::table('outbox_messages')
                ->where('status', $status)
                ->where('created_at', '<', $cutoffDate)
                ->orderBy('id', 'asc') // Deterministic Sorting for Replication Safety
                ->limit($batchSize)
                ->delete();

            $totalDeleted += $rowsAffected;

            if ($rowsAffected > 0) {
                usleep(100000);
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
