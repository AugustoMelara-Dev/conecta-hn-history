<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\Enums\MovementType;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyStockCommand extends Command
{
    protected $signature = 'vito:migrate-legacy-stock {--chunk=100}';

    protected $description = 'Migrates legacy stock_quantity into inventory snapshot and ledger tables.';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $migrated = 0;
        $skipped = 0;
        $alreadyMigrated = 0;

        Product::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('manage_stock', true)
            ->where('stock_quantity', '>', 0)
            ->with(['tenant.locations'])
            ->chunkById($chunkSize, function ($products) use (&$migrated, &$skipped, &$alreadyMigrated): void {
                foreach ($products as $product) {
                    $tenant = $product->tenant;

                    if (! $tenant instanceof Tenant) {
                        $skipped++;

                        continue;
                    }

                    $primaryLocation = $tenant->locations
                        ->where('is_primary', true)
                        ->where('is_active', true)
                        ->first();

                    if (! $primaryLocation instanceof Location) {
                        $skipped++;

                        continue;
                    }

                    DB::transaction(function () use ($product, $primaryLocation, &$migrated, &$alreadyMigrated): void {
                        $existingLevel = InventoryLevel::query()
                            ->where('location_id', $primaryLocation->id)
                            ->where('product_id', $product->id)
                            ->first();

                        $existingMovement = InventoryMovement::query()
                            ->where('location_id', $primaryLocation->id)
                            ->where('product_id', $product->id)
                            ->where('type', MovementType::INITIAL->value)
                            ->where('reference_type', 'legacy_stock_migration')
                            ->first();

                        if ($existingLevel !== null && $existingMovement !== null) {
                            $alreadyMigrated++;

                            return;
                        }

                        InventoryLevel::query()->updateOrCreate(
                            [
                                'location_id' => $primaryLocation->id,
                                'product_id' => $product->id,
                            ],
                            [
                                'tenant_id' => $product->tenant_id,
                                'on_hand' => (string) $product->stock_quantity,
                                'reserved' => '0.000',
                                'low_stock_threshold' => $product->low_stock_threshold === null
                                    ? null
                                    : number_format((float) $product->low_stock_threshold, 3, '.', ''),
                            ]
                        );

                        InventoryMovement::query()->firstOrCreate(
                            [
                                'location_id' => $primaryLocation->id,
                                'product_id' => $product->id,
                                'type' => MovementType::INITIAL->value,
                                'reference_type' => 'legacy_stock_migration',
                            ],
                            [
                                'tenant_id' => $product->tenant_id,
                                'user_id' => null,
                                'quantity_delta' => number_format((float) $product->stock_quantity, 3, '.', ''),
                                'reference_id' => null,
                                'notes' => 'Automated migration from legacy stock_quantity',
                            ]
                        );

                        $migrated++;
                    });
                }
            });

        $this->info('Migration Complete.');
        $this->line("Migrated: {$migrated}");
        $this->line("Skipped (No primary location/tenant): {$skipped}");
        $this->line("Already Migrated: {$alreadyMigrated}");

        return self::SUCCESS;
    }
}
