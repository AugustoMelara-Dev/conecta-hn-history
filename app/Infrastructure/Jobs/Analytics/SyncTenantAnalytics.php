<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\TenantAnalytic;
use App\Models\TenantVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
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
        $cache = app(CacheRepository::class);
        $totalCacheKey = "analytics:tenants:{$this->tenantId}:total:{$this->date}";
        $cachedTotalVisits = (int) ($cache->get($totalCacheKey, 0) ?? 0);

        $uniqueVisitors = TenantVisit::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereDate('created_at', $this->date)
            ->distinct('session_hash')
            ->count('session_hash');

        $record = TenantAnalytic::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->whereDate('date', $this->date)
            ->first();

        if (! $record instanceof TenantAnalytic) {
            $record = new TenantAnalytic;
            $record->forceFill([
                'tenant_id' => $this->tenantId,
                'date' => $this->date,
            ]);
        }

        $existingViews = (int) ($record->views ?? 0);
        $existingUniqueVisitors = (int) ($record->unique_visitors ?? 0);
        $existingWhatsAppClicks = (int) ($record->whatsapp_clicks ?? 0);

        $record->fill([
            'views' => max($cachedTotalVisits, $uniqueVisitors, $existingViews),
            'unique_visitors' => max($uniqueVisitors, $existingUniqueVisitors),
            'whatsapp_clicks' => $existingWhatsAppClicks,
        ]);
        $record->save();
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
