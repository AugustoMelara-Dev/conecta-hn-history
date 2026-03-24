<?php

declare(strict_types=1);

use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(\Illuminate\Foundation\Testing\DatabaseTransactions::class);

it('enforces unique location and product combinations', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main',
        'slug' => 'main',
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => '10.000',
    ]);

    expect(fn () => InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => '5.000',
    ]))->toThrow(QueryException::class);
});

it('preserves decimal precision in inventory levels', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main',
        'slug' => 'main',
    ]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $level = InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => '12.345',
        'reserved' => '0.125',
    ]);

    $freshLevel = $level->fresh();

    expect((string) $freshLevel->on_hand)->toBe('12.345')
        ->and((string) $freshLevel->reserved)->toBe('0.125');
});

it('persists the new product unit code without mutating legacy inventory fields', function () {
    $product = Product::factory()->create([
        'unit_code' => 'kg',
        'manage_stock' => true,
        'stock_quantity' => 4,
        'low_stock_threshold' => 2,
        'allow_backorders' => false,
    ]);

    $freshProduct = $product->fresh();

    expect($freshProduct->unit_code)->toBe('kg')
        ->and($freshProduct->manage_stock)->toBeTrue()
        ->and($freshProduct->stock_quantity)->toBe(4)
        ->and($freshProduct->low_stock_threshold)->toBe(2)
        ->and($freshProduct->allow_backorders)->toBeFalse();
});
