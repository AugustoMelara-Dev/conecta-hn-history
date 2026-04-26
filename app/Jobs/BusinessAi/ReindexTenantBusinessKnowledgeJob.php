<?php

declare(strict_types=1);

namespace App\Jobs\BusinessAi;

use App\Application\BusinessAi\Contracts\BusinessKnowledgeIndexerInterface;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ReindexTenantBusinessKnowledgeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $tenantId,
        public readonly ?string $requestedBy = null,
    ) {}

    public function uniqueId(): string
    {
        return 'business_ai_reindex:'.$this->tenantId;
    }

    public function handle(BusinessKnowledgeIndexerInterface $indexer): void
    {
        $tenant = Tenant::query()->whereKey($this->tenantId)->first();

        if (! $tenant instanceof Tenant) {
            Log::warning('business_rag.reindex_tenant_missing', [
                'tenant_id' => $this->tenantId,
                'requested_by' => $this->requestedBy,
            ]);

            return;
        }

        $startedAt = microtime(true);
        $result = $indexer->indexTenant($tenant);

        Log::info('business_rag.reindex_completed', [
            'tenant_id' => $this->tenantId,
            'requested_by' => $this->requestedBy,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'indexed' => (int) ($result['indexed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'sources' => $result['sources'] ?? [],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('business_rag.reindex_failed', [
            'tenant_id' => $this->tenantId,
            'requested_by' => $this->requestedBy,
            'error' => $exception->getMessage(),
        ]);
    }
}
