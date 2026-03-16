<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('backfills legacy orders with the tenant primary active location', function () {
    $tenantWithPrimary = Tenant::factory()->create();
    $tenantWithoutPrimary = Tenant::factory()->create();

    $userA = User::factory()->create(['tenant_id' => $tenantWithPrimary->id]);
    $userB = User::factory()->create(['tenant_id' => $tenantWithoutPrimary->id]);

    $primaryLocation = Location::query()->create([
        'tenant_id' => $tenantWithPrimary->id,
        'name' => 'Sucursal Centro',
        'slug' => 'sucursal-centro',
        'is_primary' => true,
        'is_active' => true,
    ]);

    $secondaryLocation = Location::query()->create([
        'tenant_id' => $tenantWithPrimary->id,
        'name' => 'Sucursal Norte',
        'slug' => 'sucursal-norte',
        'is_primary' => false,
        'is_active' => true,
    ]);

    $legacyOrder = Order::factory()->forTenant($tenantWithPrimary)->create([
        'user_id' => $userA->id,
        'location_id' => null,
    ]);

    $alreadyAssignedOrder = Order::factory()->forTenant($tenantWithPrimary)->create([
        'user_id' => $userA->id,
        'location_id' => $secondaryLocation->id,
    ]);

    $orphanedOrder = Order::factory()->forTenant($tenantWithoutPrimary)->create([
        'user_id' => $userB->id,
        'location_id' => null,
    ]);

    $this->artisan('vito:backfill-order-locations', ['--chunk' => 1])
        ->expectsOutput('Backfill complete. Updated: 1. Skipped: 1. Remaining without location: 1.')
        ->assertExitCode(0);

    expect($legacyOrder->fresh()->location_id)->toBe($primaryLocation->id)
        ->and($alreadyAssignedOrder->fresh()->location_id)->toBe($secondaryLocation->id)
        ->and($orphanedOrder->fresh()->location_id)->toBeNull();

    $this->artisan('vito:backfill-order-locations')
        ->expectsOutput('Backfill complete. Updated: 0. Skipped: 1. Remaining without location: 1.')
        ->assertExitCode(0);
});
