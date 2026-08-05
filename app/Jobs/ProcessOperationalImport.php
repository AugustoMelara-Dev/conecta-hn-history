<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Imports\OperationalImportService;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ProcessOperationalImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $actorId,
        public readonly string $batchId,
        public readonly string $correlationId,
        public readonly string $idempotencyKey,
    ) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(OperationalImportService $service): void
    {
        $service->process($this->tenantId, $this->batchId);
    }
}
