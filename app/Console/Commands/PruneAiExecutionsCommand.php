<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneAiExecutionsCommand extends Command
{
    protected $signature = 'ai:prune-executions {--limit=1000 : Maximum terminal execution payloads to erase}';

    protected $description = 'Erase expired AI payload ciphertext while retaining the immutable accounting ledger.';

    public function handle(): int
    {
        $limit = min(10_000, max(1, (int) $this->option('limit')));
        $ids = DB::table('ai_executions')
            ->leftJoin('ai_credit_reservations', 'ai_credit_reservations.id', '=', 'ai_executions.reservation_id')
            ->whereIn('ai_executions.status', ['completed', 'failed'])
            ->where('ai_executions.retention_expires_at', '<=', now())
            ->whereNull('ai_executions.payload_pruned_at')
            ->whereNotNull('ai_executions.result_ciphertext')
            ->where(static function ($query): void {
                $query->whereNull('ai_executions.reservation_id')
                    ->orWhereIn('ai_credit_reservations.status', ['settled', 'refunded']);
            })
            ->orderBy('ai_executions.retention_expires_at')
            ->limit($limit)
            ->pluck('ai_executions.correlation_id');

        $pruned = $ids->isEmpty()
            ? 0
            : DB::table('ai_executions')
                ->whereIn('correlation_id', $ids->all())
                ->whereNull('payload_pruned_at')
                ->update([
                    'result_ciphertext' => null,
                    'result_hash' => null,
                    'payload_pruned_at' => now(),
                    'updated_at' => now(),
                ]);

        $this->info("Expired AI execution payloads pruned: {$pruned}.");

        return self::SUCCESS;
    }
}
