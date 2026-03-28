<?php

declare(strict_types=1);

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CouponPolicy;
use Filament\Facades\Filament;

test('coupon policy denies semilla tenants', function () {
    $tenant = new Tenant;
    $tenant->forceFill([
        'id' => '00000000-0000-0000-0000-000000000001',
        'plan_type' => TenantPlan::SEMILLA,
        'subscription_status' => 'active',
    ]);

    Filament::shouldReceive('getTenant')->andReturn($tenant);

    $user = new User;

    $response = (new CouponPolicy)->create($user);

    expect($response->allowed())->toBeFalse()
        ->and($response->message())->toContain('característica PRO');
});

test('coupon policy allows paid plans from plan type', function () {
    $tenant = new Tenant;
    $tenant->forceFill([
        'id' => '00000000-0000-0000-0000-000000000002',
        'plan_type' => TenantPlan::PRO,
        'subscription_status' => 'active',
    ]);

    Filament::shouldReceive('getTenant')->andReturn($tenant);

    $user = new User;

    $response = (new CouponPolicy)->create($user);

    expect($response->allowed())->toBeTrue();
});
