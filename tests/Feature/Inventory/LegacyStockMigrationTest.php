<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Enums\MovementType;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LegacyStockMigrationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_migrates_legacy_stock_to_the_primary_location_idempotently(): void
    {
        $tenant = Tenant::factory()->create();
        $location = Location::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'manage_stock' => true,
            'stock_quantity' => 15,
            'low_stock_threshold' => 5,
        ]);

        $this->artisan('vito:migrate-legacy-stock')
            ->expectsOutputToContain('Migrated: 1')
            ->assertSuccessful();

        $level = InventoryLevel::query()->where('product_id', $product->id)->first();
        self::assertInstanceOf(InventoryLevel::class, $level);
        self::assertSame('15.000', (string) $level->on_hand);
        self::assertSame('0.000', (string) $level->reserved);
        self::assertSame('5.000', (string) $level->low_stock_threshold);
        self::assertSame((string) $location->id, (string) $level->location_id);

        $movement = InventoryMovement::query()->where('product_id', $product->id)->first();
        self::assertInstanceOf(InventoryMovement::class, $movement);
        self::assertSame('15.000', (string) $movement->quantity_delta);
        self::assertSame(MovementType::INITIAL, $movement->type);

        $this->artisan('vito:migrate-legacy-stock')
            ->expectsOutputToContain('Already Migrated: 1')
            ->assertSuccessful();

        self::assertSame(1, InventoryLevel::query()->count());
        self::assertSame(1, InventoryMovement::query()->count());
    }

    #[Test]
    public function it_skips_managed_products_when_the_tenant_has_no_active_primary_location(): void
    {
        $tenant = Tenant::factory()->create();

        Location::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dormant',
            'slug' => 'dormant',
            'is_primary' => true,
            'is_active' => false,
        ]);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'manage_stock' => true,
            'stock_quantity' => 7,
        ]);

        $this->artisan('vito:migrate-legacy-stock')
            ->expectsOutputToContain('Skipped (No primary location/tenant):')
            ->assertSuccessful();

        self::assertFalse(InventoryLevel::query()->where('product_id', $product->id)->exists());
        self::assertFalse(InventoryMovement::query()->where('product_id', $product->id)->exists());
    }
}
