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
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

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

it('allows extra membership permissions granted by an operational preset', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::SERVICE,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => Role::OPERATOR->value,
        'preset_key' => 'dental_reception',
        'granted_permissions' => [
            Permission::APPOINTMENTS_MANAGE->value,
            Permission::MARKETING_MANAGE->value,
        ],
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

it('allows managers to assign playbooks when the tenant supports operational notifications', function () {
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
        Capability::NOTIFICATIONS_BASIC,
        Permission::PLAYBOOKS_ASSIGN
    ))->toBeTrue();
});

it('keeps viewers in read-only mode for playbook execution', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'business_type' => BusinessType::SERVICE,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => Role::VIEWER->value,
        'accepted_at' => now(),
    ]);

    $guard = app(TenantPermissionGuardInterface::class);

    expect($guard->allows(
        $user,
        $tenant,
        Capability::NOTIFICATIONS_BASIC,
        Permission::PLAYBOOKS_VIEW
    ))->toBeTrue()
        ->and($guard->allows(
            $user,
            $tenant,
            Capability::NOTIFICATIONS_BASIC,
            Permission::PLAYBOOKS_MANAGE
        ))->toBeFalse();
});

it('enforces module-level permission matrix for manager role', function (Capability $capability, Permission $permission) {
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

    expect($guard->allows($user, $tenant, $capability, $permission))->toBeTrue();
})->with([
    'sales' => [Capability::CHECKOUT_BASIC, Permission::ORDERS_MANAGE],
    'agenda' => [Capability::APPOINTMENT_BOOKING, Permission::APPOINTMENTS_MANAGE],
    'customers' => [Capability::CUSTOMER_DIRECTORY, Permission::CUSTOMERS_VIEW],
    'marketing' => [Capability::NOTIFICATIONS_BASIC, Permission::MARKETING_MANAGE],
    'finance' => [Capability::PAYMENTS_BASIC, Permission::PAYMENTS_VIEW],
    'configuration' => [Capability::CATALOG_BASIC, Permission::SETTINGS_VIEW],
    'administration' => [Capability::STAFF_BASIC, Permission::STAFF_VIEW],
]);

it('denies cross-tenant access even when user has valid membership on another tenant', function () {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create([
        'business_type' => BusinessType::SERVICE,
    ]);
    $tenantB = Tenant::factory()->create([
        'business_type' => BusinessType::SERVICE,
    ]);

    TenantMembership::query()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $user->id,
        'role' => Role::MANAGER->value,
        'accepted_at' => now(),
    ]);

    $guard = app(TenantPermissionGuardInterface::class);

    expect($guard->allows(
        $user,
        $tenantB,
        Capability::APPOINTMENT_BOOKING,
        Permission::APPOINTMENTS_MANAGE
    ))->toBeFalse();
});
