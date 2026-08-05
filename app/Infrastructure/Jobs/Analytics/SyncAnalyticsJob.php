<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SyncAnalyticsJob - Batch Analytics Coordinator
 *
 * INFRASTRUCTURE JOB: Coordinates batch analytics sync across all tenants.
 * IDEMPOTENCY: Uses ShouldBeUnique with daily date key to prevent duplicate runs.
 */
final class SyncAnalyticsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    /**
     * Unique job identifier (daily granularity).
     */
    public function uniqueId(): string
    {
        return 'sync_analytics_'.now()->subDay()->format('Y-m-d');
    }

    public function handle(): void
    {
        $date = now()->subDay()->format('Y-m-d');
        Log::info("SyncAnalyticsJob: Starting for {$date}");

        $processedCount = 0;

        Tenant::where('status', 'approved')
            ->chunkById(500, function ($tenants) use ($date, &$processedCount) {
                foreach ($tenants as $tenant) {
                    SyncTenantAnalytics::dispatch((string) $tenant->id, $date);
                    $processedCount++;
                }
            });

        Log::info("SyncAnalyticsJob: Queued {$processedCount} tenant aggregates", [
            'date' => $date,
            'processed_tenants' => $processedCount,
        ]);
    }

    public function tags(): array
    {
        return ['analytics', 'sync', 'coordinator'];
    }
}
