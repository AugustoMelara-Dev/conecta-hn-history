<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\TenantAnalytic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * SyncTenantAnalytics - Tenant-scoped Analytics Sync
 *
 * INFRASTRUCTURE JOB: Uses updateOrCreate for atomic upsert operations.
 */
final class SyncTenantAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $date
    ) {}

    public function handle(): void
    {
        TenantAnalytic::updateOrCreate(
            ['tenant_id' => $this->tenantId, 'date' => $this->date],
            [
                'views' => 0,
                'whatsapp_clicks' => 0,
                'unique_visitors' => 0,
            ]
        );
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['analytics', 'tenant:'.$this->tenantId];
    }
}
