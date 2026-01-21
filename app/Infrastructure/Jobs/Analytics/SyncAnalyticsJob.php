<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Analytics;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * SyncAnalyticsJob - Batch Analytics Coordinator
 *
 * INFRASTRUCTURE JOB: Coordinates batch analytics sync across all tenants.
 */
final class SyncAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public function handle(): void
    {
        $date = now()->subDay()->format('Y-m-d');
        Log::info("SyncAnalyticsJob: Starting for {$date}");

        $processedCount = 0;

        Tenant::where('status', 'approved')
            ->chunkById(500, function ($tenants) use ($date, &$processedCount) {
                foreach ($tenants as $tenant) {
                    SyncTenantAnalytics::dispatch($tenant->id, $date);
                    $processedCount++;
                }
            });

        // T-103: Instrument Process
        event(new \App\Domain\Analytics\Events\AnalyticsSynced(
            tenantId: 0, // System context or aggregate 0
            processedRecords: $processedCount,
            occurredOn: new \DateTimeImmutable
        ));
    }

    public function tags(): array
    {
        return ['analytics', 'sync', 'coordinator'];
    }
}
