<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantAnalytic;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class SyncAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * Pilar 8: Sync Redis HyperLogLog data to PostgreSQL
     */
    public function handle(): void
    {
        // Process yesterday's data (give time for all visits to be recorded)
        $date = now()->subDay()->format('Y-m-d');

        \Log::info("SyncAnalyticsJob: Processing analytics for {$date}");

        // Get all active tenants
        Tenant::where('status', 'approved')
            ->chunk(100, function ($tenants) use ($date) {
                foreach ($tenants as $tenant) {
                    try {
                        $uniqueKey = "analytics:tenants:{$tenant->id}:visits:{$date}";
                        $totalKey = "analytics:tenants:{$tenant->id}:total:{$date}";

                        // Get counts from Redis
                        $uniqueVisits = Redis::pfcount($uniqueKey);
                        $totalVisits = Redis::get($totalKey) ?? 0;

                        // Only save if there were visits
                        if ($uniqueVisits > 0 || $totalVisits > 0) {
                            TenantAnalytic::updateOrCreate(
                                [
                                    'tenant_id' => $tenant->id,
                                    'date' => $date,
                                ],
                                [
                                    'unique_visits' => $uniqueVisits,
                                    'total_visits' => $totalVisits,
                                ]
                            );

                            \Log::info("Synced analytics for tenant {$tenant->id}: {$uniqueVisits} unique, {$totalVisits} total");
                        }

                        // Optional: Delete Redis keys after sync to save memory
                        // Redis::del($uniqueKey, $totalKey);

                    } catch (\Exception $e) {
                        \Log::error("Failed to sync analytics for tenant {$tenant->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        \Log::info("SyncAnalyticsJob: Completed for {$date}");
    }
}
