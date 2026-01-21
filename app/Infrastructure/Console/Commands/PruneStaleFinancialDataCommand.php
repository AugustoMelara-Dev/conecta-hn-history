<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneStaleFinancialDataCommand extends Command
{
    protected $signature = 'billing:prune-data';
    protected $description = 'Safely prunes stale financial ledger entries (> 30 days) using Timestamp-Index Batching';

    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $this->info('Starting Safe Financial Data Hygiene Pruning...');

        $threshold = now()->subDays(30); 
        $totalDeleted = 0;

        try {
            // SCOPE 1: Subscription Intents
            // STRATEGY: 'Delete Limit' with 'OrderBy CreatedAt'.
            // ordering by 'id' (UUID) is performance suicide (Random I/O).
            // ordering by 'created_at' uses the natural time-series index, 
            // making finding deletion candidates extremely fast.
            
            do {
                 $affected = DB::table('subscription_intents')
                    ->where('created_at', '<', $threshold)
                    ->orderBy('created_at') // Use the Time Index
                    ->limit(self::BATCH_SIZE)
                    ->delete();

                $totalDeleted += $affected;
                
                if ($affected > 0) {
                     $this->info("Pruned batch: {$affected} rows deleted. (Total: {$totalDeleted})");
                     usleep(10000); 
                }

            } while ($affected > 0);

            $this->info("Pruning Complete. Total records removed: {$totalDeleted}");
            Log::info("Billing Janitor: Pruned {$totalDeleted} stale intents.");

        } catch (\Throwable $e) {
            $this->error("Pruning failed: " . $e->getMessage());
            Log::error("Billing Janitor Failed", ['exception' => $e]);
            return 1;
        }

        return 0;
    }
}
