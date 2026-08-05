<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Application\Products\CanonicalProductWriter;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Application\Markets\MissingMarketContextException;
use App\Models\Tenant;
use Tests\Support\GlobalMarketFixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CanonicalProductWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_requires_explicit_market_context(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);

        $this->expectException(MissingMarketContextException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            (string) $tenant->id,
            'catalog-market-required',
            'No silent market',
            100,
        ));
    }

    public function test_canadian_create_persists_canonical_currency_and_minor_units(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        GlobalMarketFixture::assignCanada($tenant);

        $product = app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            (string) $tenant->id,
            'catalog-canada',
            'Canadian product',
            12_345,
        ));

        self::assertSame('CAD', $product->currency);
        self::assertSame(12_345, $product->price_minor);
        self::assertSame('123.45', (string) $product->getRawOriginal('price'));
    }

    public function test_price_minor_must_fit_decimal_ten_two(): void
    {
        $tenant = $this->marketTenant();

        $this->expectException(\InvalidArgumentException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            tenantId: (string) $tenant->getKey(),
            idempotencyKey: 'catalog-price-overflow',
            name: 'Fuera de rango',
            priceMinor: 10_000_000_000,
        ));
    }

    public function test_create_uses_uuid_tenant_exact_minor_price_and_one_initial_movement(): void
    {
        $tenant = $this->marketTenant();

        $product = app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            tenantId: (string) $tenant->getKey(),
            idempotencyKey: 'catalog-create-001',
            name: 'Café de prueba',
            priceMinor: 12345,
            initialStockQuantity: 7,
        ));

        $this->assertSame((string) $tenant->getKey(), (string) $product->tenant_id);
        $this->assertSame('123.45', (string) $product->getRawOriginal('price'));
        $this->assertSame(7, (int) $product->stock_quantity);
        $this->assertSame(1, DB::table('inventory_movements')->where('product_id', $product->getKey())->count());
    }

    public function test_create_retry_replays_and_rejects_an_incompatible_payload(): void
    {
        $tenant = $this->marketTenant();
        $writer = app(CanonicalProductWriter::class);
        $command = new CreateCanonicalProductCommand((string) $tenant->getKey(), 'catalog-create-replay', 'Taza', 500, 2);

        $first = $writer->create($command);
        $second = $writer->create($command);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, DB::table('products')->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(1, DB::table('inventory_movements')->where('product_id', $first->getKey())->count());

        $this->expectException(\LogicException::class);
        $writer->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'catalog-create-replay', 'Taza', 501, 2));
    }

    public function test_tenant_scoped_update_delete_and_adjustment_do_not_cross_tenants(): void
    {
        $owner = $this->marketTenant();
        $other = $this->marketTenant();
        $writer = app(CanonicalProductWriter::class);
        $product = $writer->create(new CreateCanonicalProductCommand((string) $owner->getKey(), 'catalog-cross-tenant', 'Taza', 500));

        try {
            $writer->update((string) $other->getKey(), (int) $product->getKey(), ['name' => 'Hostil']);
            $this->fail('Cross-tenant update must be hidden as not found.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame('Taza', $product->fresh()->name);
        }

        $adjusted = $writer->adjustStock((string) $owner->getKey(), (int) $product->getKey(), 3, 'manual_adjustment', 'adjust-001');
        $writer->adjustStock((string) $owner->getKey(), (int) $product->getKey(), 3, 'manual_adjustment', 'adjust-001');
        $this->assertSame(3, (int) $adjusted->stock_quantity);
        $this->assertSame(1, DB::table('inventory_movements')->where('product_id', $product->getKey())->count());
    }

    public function test_missing_update_never_creates_and_cross_tenant_delete_or_publish_is_hidden(): void
    {
        $owner = $this->marketTenant();
        $other = $this->marketTenant();
        $writer = app(CanonicalProductWriter::class);
        $product = $writer->create(new CreateCanonicalProductCommand((string) $owner->getKey(), 'catalog-missing', 'Vaso', 100));

        foreach ([
            fn () => $writer->update((string) $owner->getKey(), 999999, ['name' => 'No crear']),
            fn () => $writer->delete((string) $other->getKey(), (int) $product->getKey()),
            fn () => $writer->publish((string) $other->getKey(), (int) $product->getKey()),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The tenant-scoped write must be hidden as not found.');
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                // expected
            }
        }

        $this->assertDatabaseCount('products', 1);
        $this->assertNull($product->fresh()->deleted_at);
        $this->assertFalse((bool) $product->fresh()->is_visible);
    }

    public function test_incompatible_stock_adjustment_replay_is_rejected_without_legacy_stock_column(): void
    {
        $tenant = $this->marketTenant();
        $writer = app(CanonicalProductWriter::class);
        $product = $writer->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'catalog-adjust-incompatible', 'Plato', 100));

        $writer->adjustStock((string) $tenant->getKey(), (int) $product->getKey(), 2, 'manual_adjustment', 'adjust-incompatible');
        try {
            $writer->adjustStock((string) $tenant->getKey(), (int) $product->getKey(), 3, 'manual_adjustment', 'adjust-incompatible');
            $this->fail('An incompatible inventory replay must fail.');
        } catch (\LogicException) {
            $this->assertSame(2, (int) $product->fresh()->stock_quantity);
            $this->assertSame(1, DB::table('inventory_movements')->where('product_id', $product->getKey())->count());
            $this->assertFalse(array_key_exists('stock', $product->fresh()->getAttributes()));
        }
    }

    public function test_writer_rejects_a_menu_section_owned_by_another_tenant(): void
    {
        $owner = $this->marketTenant();
        $other = $this->marketTenant();
        $sectionId = DB::table('menu_sections')->insertGetId([
            'tenant_id' => $other->getKey(), 'name' => 'Ajena', 'is_active' => true,
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            (string) $owner->getKey(), 'foreign-menu-section', 'Seguro', 100, 0,
            ['menu_section_id' => $sectionId],
        ));
    }

    public function test_capacity_is_enforced_after_locking_the_tenant(): void
    {
        $tenant = $this->marketTenant(['product_limit' => 0]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'capacity-lock', 'No cabe', 100));
    }

    public function test_create_attributes_cannot_smuggle_price_minor(): void
    {
        $tenant = $this->marketTenant();
        $this->expectException(\InvalidArgumentException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'smuggled-price', 'Seguro', 100, 0, ['price_minor' => 1]));
    }

    public function test_nested_attribute_key_order_does_not_change_create_replay_hash(): void
    {
        $tenant = $this->marketTenant();
        $writer = app(CanonicalProductWriter::class);
        $first = $writer->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'nested-hash', 'Orden', 100, 0, ['dimensions' => ['width' => 1, 'height' => 2]]));
        $second = $writer->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'nested-hash', 'Orden', 100, 0, ['dimensions' => ['height' => 2, 'width' => 1]]));
        $this->assertSame($first->getKey(), $second->getKey());
    }

    public function test_unmanaged_product_is_persisted_and_cannot_declare_initial_stock(): void
    {
        $tenant = $this->marketTenant();
        $product = app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            (string) $tenant->getKey(), 'unmanaged-product', 'Servicio', 100, 0, ['manage_stock' => false],
        ));
        $this->assertFalse((bool) $product->manage_stock);
        $this->assertSame(\App\Enums\StockStatus::InStock, $product->stock_status);

        $this->expectException(\InvalidArgumentException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand(
            (string) $tenant->getKey(), 'unmanaged-with-stock', 'Servicio', 100, 1, ['manage_stock' => false],
        ));
    }

    public function test_direct_non_boolean_manage_stock_is_rejected(): void
    {
        $tenant = $this->marketTenant();
        $this->expectException(\InvalidArgumentException::class);
        app(CanonicalProductWriter::class)->create(new CreateCanonicalProductCommand((string) $tenant->getKey(), 'invalid-manage-stock', 'Servicio', 100, 0, ['manage_stock' => 0]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function marketTenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::factory()->create(['city_id' => null] + $attributes);
        GlobalMarketFixture::assignCanada($tenant);

        return $tenant;
    }
}
