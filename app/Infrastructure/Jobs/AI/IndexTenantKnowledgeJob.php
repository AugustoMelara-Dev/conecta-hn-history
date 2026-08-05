<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Application\Ai\Knowledge\CatalogKnowledgeIndexer;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class IndexTenantKnowledgeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 120;

    public function __construct(public readonly string $tenantId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'knowledge:'.$this->tenantId;
    }

    public function handle(CatalogKnowledgeIndexer $indexer): void
    {
        $indexer->indexTenant($this->tenantId);
    }

    /** @return list<TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId, allowTrashed: true)];
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['ai', 'knowledge', 'tenant:'.$this->tenantId];
    }
}
