<?php

declare(strict_types=1);

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows the legacy owner fallback when the tenant has the capability', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->forUser($user)->create([
        'business_type' => BusinessType::RETAIL,
    ]);

    $guard = app(TenantPermissionGuardInterface::class);

    expect($guard->allows(
        $user,
        $tenant,
        Capability::CHECKOUT_BASIC,
        Permission::BILLING_MANAGE
    ))->toBeTrue();
});

it('allows a manager membership when the tenant capability and role permission align', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::SERVICE,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => Role::MANAGER->value,
        'accepted_at' => now(),
    ]);

    $guard = app(TenantPermissionGuardInterface::class);

    expect($guard->allows(
        $user,
        $tenant,
        Capability::APPOINTMENT_BOOKING,
        Permission::APPOINTMENTS_MANAGE
    ))->toBeTrue();
});

it('denies access when the tenant lacks the capability even if the role would allow the permission', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::RETAIL,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => Role::MANAGER->value,
        'accepted_at' => now(),
    ]);

    $guard = app(TenantPermissionGuardInterface::class);

    expect($guard->allows(
        $user,
        $tenant,
        Capability::APPOINTMENT_BOOKING,
        Permission::APPOINTMENTS_MANAGE
    ))->toBeFalse();
});
