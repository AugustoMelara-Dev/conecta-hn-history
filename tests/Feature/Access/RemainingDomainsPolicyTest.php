<?php

declare(strict_types=1);

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies appointment management if user lacks permission via guard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $guard = Mockery::mock(TenantPermissionGuardInterface::class);
    $guard->shouldReceive('allows')
        ->with($user, $tenant, Capability::APPOINTMENT_BOOKING, Permission::APPOINTMENTS_MANAGE)
        ->andReturn(false);

    Filament::shouldReceive('getTenant')->andReturn($tenant);

    $policy = new AppointmentPolicy($guard);

    expect($policy->create($user))->toBeFalse();
});

it('allows appointment management if user has permission via guard', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    $guard = Mockery::mock(TenantPermissionGuardInterface::class);
    $guard->shouldReceive('allows')
        ->with($user, $tenant, Capability::APPOINTMENT_BOOKING, Permission::APPOINTMENTS_MANAGE)
        ->andReturn(true);

    Filament::shouldReceive('getTenant')->andReturn($tenant);

    $policy = new AppointmentPolicy($guard);

    expect($policy->create($user))->toBeTrue();
});
