<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a location scoped to a tenant', function () {
    $tenant = Tenant::factory()->create();

    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Test Branch',
        'slug' => 'test-branch',
        'is_primary' => true,
    ]);

    expect($location->tenant_id)->toBe($tenant->id)
        ->and($location->is_primary)->toBeTrue();
});

it('can associate an order with a location', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Branch',
        'slug' => 'main-branch',
    ]);

    $order = Order::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);

    expect($order->location_id)->toBe($location->id)
        ->and($order->location->name)->toBe('Main Branch');
});
