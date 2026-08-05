<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Interaction;
use App\Models\TenantAnalytic;
use App\Models\TenantVisit;
use Carbon\CarbonImmutable;
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
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $this->date);
        if ($day === null || $day->format('Y-m-d') !== $this->date) {
            throw new \InvalidArgumentException('Analytics date must use Y-m-d format.');
        }

        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $visits = TenantVisit::query()
            ->where('tenant_id', $this->tenantId)
            ->whereBetween('created_at', [$start, $end]);

        $views = (clone $visits)->count();
        $uniqueVisitors = (clone $visits)->distinct()->count('session_hash');
        $whatsappClicks = Interaction::query()
            ->where('tenant_id', $this->tenantId)
            ->where('type', Interaction::TYPE_WHATSAPP_CLICK)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $aggregate = TenantAnalytic::query()
            ->where('tenant_id', $this->tenantId)
            ->whereDate('date', $this->date)
            ->first() ?? new TenantAnalytic;

        $aggregate->forceFill([
            'tenant_id' => $this->tenantId,
            'date' => $this->date,
            'views' => $views,
            'unique_visitors' => $uniqueVisitors,
            'whatsapp_clicks' => $whatsappClicks,
        ])->save();
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
