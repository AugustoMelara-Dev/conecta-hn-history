<?php

declare(strict_types=1);

use App\Domain\Inventory\Enums\MovementType;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('migrates legacy stock to the primary location idempotently', function () {
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

    expect($level)->not->toBeNull()
        ->and((string) $level->on_hand)->toBe('15.000')
        ->and((string) $level->reserved)->toBe('0.000')
        ->and((string) $level->low_stock_threshold)->toBe('5.000')
        ->and($level->location_id)->toBe($location->id);

    $movement = InventoryMovement::query()->where('product_id', $product->id)->first();

    expect($movement)->not->toBeNull()
        ->and((string) $movement->quantity_delta)->toBe('15.000')
        ->and($movement->type)->toBe(MovementType::INITIAL);

    $this->artisan('vito:migrate-legacy-stock')
        ->expectsOutputToContain('Already Migrated: 1')
        ->assertSuccessful();

    expect(InventoryLevel::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1);
});

it('skips managed products when the tenant has no active primary location', function () {
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
        ->expectsOutputToContain('Skipped (No primary location/tenant): 1')
        ->assertSuccessful();

    expect(InventoryLevel::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(InventoryMovement::query()->where('product_id', $product->id)->exists())->toBeFalse();
});
