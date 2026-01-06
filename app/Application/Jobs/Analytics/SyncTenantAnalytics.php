<?php

declare(strict_types=1);

namespace App\Application\Jobs\Analytics;

use App\Models\TenantAnalytic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;

final class SyncTenantAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $date
    ) {}

    public function handle(): void
    {
        TenantAnalytic::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'date' => $this->date],
            ['synced_at' => now()]
        );
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['analytics', 'tenant:' . $this->tenantId];
    }
}
