<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Shared\Services\TenantContext;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\Tenant;

beforeEach(function () {
    app(TenantContext::class)->setAsSystemRoot(true);
});

afterEach(function () {
    app(TenantContext::class)->reset();
});

it('calculates delta correctly when reserving stock', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main',
        'slug' => 'main',
        'is_active' => true,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'manage_stock' => true,
        'allow_backorders' => false,
    ]);

    InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => 10.000,
        'reserved' => 0.000,
    ]);

    $port = app(InventoryHoldPortInterface::class);
    $sessionId = 'cart_session_123';

    $port->reserve($tenant->id, $location->id, $sessionId, $product->id, 2.000);
    $level = InventoryLevel::where('product_id', $product->id)->firstOrFail();
    expect((string) $level->reserved)->toBe('2.000');

    $port->reserve($tenant->id, $location->id, $sessionId, $product->id, 5.000);
    $level->refresh();
    expect((string) $level->reserved)->toBe('5.000');

    $port->reserve($tenant->id, $location->id, $sessionId, $product->id, 1.000);
    $level->refresh();
    expect((string) $level->reserved)->toBe('1.000');
});

it('commits hold by reducing on_hand and writing movement ledger', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main',
        'slug' => 'main',
        'is_active' => true,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'manage_stock' => true,
        'allow_backorders' => false,
    ]);

    InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => 10.000,
        'reserved' => 0.000,
    ]);

    $port = app(InventoryHoldPortInterface::class);
    $sessionId = 'cart_session_123';

    $port->reserve($tenant->id, $location->id, $sessionId, $product->id, 4.000);
    $port->commit($tenant->id, $sessionId, 'order-uuid-abc');

    $level = InventoryLevel::where('product_id', $product->id)->firstOrFail();
    expect((string) $level->on_hand)->toBe('6.000')
        ->and((string) $level->reserved)->toBe('0.000');

    $movement = InventoryMovement::where('product_id', $product->id)->firstOrFail();
    expect((string) $movement->quantity_delta)->toBe('-4.000')
        ->and($movement->type)->toBe(MovementType::SALE)
        ->and($movement->reference_id)->toBe('order-uuid-abc');
});

it('blocks reservation if delta exceeds available stock', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main',
        'slug' => 'main',
        'is_active' => true,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'manage_stock' => true,
        'allow_backorders' => false,
    ]);

    InventoryLevel::create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'on_hand' => 10.000,
        'reserved' => 0.000,
    ]);

    $port = app(InventoryHoldPortInterface::class);
    $sessionId = 'cart_session_123';

    expect(fn () => $port->reserve($tenant->id, $location->id, $sessionId, $product->id, 11.000))
        ->toThrow(\DomainException::class);
});
