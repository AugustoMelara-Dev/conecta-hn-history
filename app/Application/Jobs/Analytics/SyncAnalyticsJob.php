<?php

declare(strict_types=1);

namespace App\Application\Jobs\Analytics;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class SyncAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public function handle(): void
    {
        $date = now()->subDay()->format('Y-m-d');
        Log::info("SyncAnalyticsJob: Starting for {$date}");

        Tenant::where('status', 'approved')
            ->chunkById(500, function ($tenants) use ($date) {
                foreach ($tenants as $tenant) {
                    SyncTenantAnalytics::dispatch($tenant->id, $date);
                }
            });
    }

    public function tags(): array
    {
        return ['analytics', 'sync', 'coordinator'];
    }
}
