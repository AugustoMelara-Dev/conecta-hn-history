<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Jobs\AI\IndexTenantKnowledgeJob;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ReindexAiKnowledgeCommand extends Command
{
    protected $signature = 'ai:knowledge:reindex
        {--tenant= : Reindex one tenant UUID}
        {--chunk=100 : Tenants dispatched per database chunk}';

    protected $description = 'Queue a tenant-scoped rebuild of the AI knowledge index';

    public function handle(): int
    {
        if (! config('ai.knowledge.enabled', false)) {
            $this->error('AI knowledge indexing is disabled.');

            return self::FAILURE;
        }

        $tenantId = trim((string) $this->option('tenant'));
        if ($tenantId !== '') {
            if (! Str::isUuid($tenantId)
                // MIGRATION_OR_BACKFILL: operator UUID is validated and constrained by primary key.
                || ! Tenant::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereKey($tenantId)->exists()) {
                $this->error('The requested tenant does not exist.');

                return self::FAILURE;
            }

            IndexTenantKnowledgeJob::dispatch($tenantId);
            $this->info('Queued knowledge reindex for one tenant.');

            return self::SUCCESS;
        }

        $chunk = max(1, min(1000, (int) $this->option('chunk')));
        $queued = 0;
        Tenant::query()
            // MIGRATION_OR_BACKFILL: global command enumerates tenants only to dispatch scoped jobs.
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->select('id')
            ->orderBy('id')
            ->chunk($chunk, function ($tenants) use (&$queued): void {
                foreach ($tenants as $tenant) {
                    IndexTenantKnowledgeJob::dispatch((string) $tenant->getKey());
                    $queued++;
                }
            });

        $this->info("Queued knowledge reindex for {$queued} tenants.");

        return self::SUCCESS;
    }
}
