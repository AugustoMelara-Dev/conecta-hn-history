<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PruneAiToolConfirmationsCommand extends Command
{
    protected $signature = 'ai:prune-tool-confirmations
        {--days=30 : Days to retain terminal or expired confirmation records}
        {--limit=1000 : Maximum confirmations to delete per run}';

    protected $description = 'Delete expired AI tool confirmation ciphertext after its retention period.';

    public function handle(): int
    {
        $days = min(365, max(1, (int) $this->option('days')));
        $limit = min(10_000, max(1, (int) $this->option('limit')));
        $connectionName = $this->connectionName();
        $connection = DB::connection($connectionName);

        if (! Schema::connection($connectionName)->hasTable('ai_tool_confirmations')) {
            $this->info('AI tool confirmation table is not installed.');

            return self::SUCCESS;
        }

        $ids = $connection->table('ai_tool_confirmations')
            ->where('created_at', '<=', now()->subDays($days))
            ->where(function ($query): void {
                $query->whereIn('status', ['completed', 'cancelled', 'expired'])
                    ->orWhere(function ($pending): void {
                        $pending->where('status', 'pending')
                            ->where('expires_at', '<=', now());
                    });
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        $deleted = $ids->isEmpty()
            ? 0
            : $connection->table('ai_tool_confirmations')->whereIn('id', $ids->all())->delete();

        $this->info("Expired AI tool confirmations pruned: {$deleted}.");

        return self::SUCCESS;
    }

    private function connectionName(): ?string
    {
        if (DB::connection()->getDriverName() === 'pgsql'
            && (bool) config('tenancy.postgres_rls.enabled', false)) {
            return 'pgsql_platform';
        }

        return null;
    }
}
