<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class BackfillOrderLocationsCommand extends Command
{
    protected $signature = 'vito:backfill-order-locations {--chunk=100 : Orders per chunk}';

    protected $description = 'Assign primary tenant locations to legacy orders missing location_id';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $updated = 0;
        $skipped = 0;

        Order::query()
            ->withoutGlobalScopes()
            ->select(['id', 'tenant_id'])
            ->whereNull('location_id')
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $orders) use (&$updated, &$skipped): void {
                $tenantIds = $orders
                    ->pluck('tenant_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $primaryLocations = Location::query()
                    ->whereIn('tenant_id', $tenantIds)
                    ->where('is_primary', true)
                    ->where('is_active', true)
                    ->pluck('id', 'tenant_id');

                $updatesByLocation = [];

                foreach ($orders as $order) {
                    $locationId = $primaryLocations->get($order->tenant_id);

                    if ($locationId === null) {
                        $skipped++;

                        continue;
                    }

                    $updatesByLocation[(string) $locationId][] = $order->id;
                }

                foreach ($updatesByLocation as $locationId => $orderIds) {
                    $affected = Order::query()
                        ->withoutGlobalScopes()
                        ->whereIn('id', $orderIds)
                        ->whereNull('location_id')
                        ->update(['location_id' => $locationId]);

                    $updated += $affected;
                }
            });

        $remaining = Order::query()
            ->withoutGlobalScopes()
            ->whereNull('location_id')
            ->count();

        $this->info("Backfill complete. Updated: {$updated}. Skipped: {$skipped}. Remaining without location: {$remaining}.");

        return self::SUCCESS;
    }
}
