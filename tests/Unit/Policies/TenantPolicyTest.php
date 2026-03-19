<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;

function makeTenantPolicyUser(UserRole $role): User
{
    $user = new User;
    $user->forceFill([
        'id' => fake()->uuid(),
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'role' => $role,
    ]);

    return $user;
}

function makeTenantPolicyTenant(string $tenantId = '00000000-0000-0000-0000-000000000001'): Tenant
{
    $tenant = new Tenant;
    $tenant->forceFill(['id' => $tenantId]);

    return $tenant;
}

test('super admin can viewAny tenants', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);

    expect((new TenantPolicy)->viewAny($user))->toBeTrue();
});

test('tenant owner is denied viewAny tenants', function () {
    $user = makeTenantPolicyUser(UserRole::TENANT_OWNER);

    expect((new TenantPolicy)->viewAny($user))->toBeFalse();
});

test('staff is denied viewAny tenants', function () {
    $user = makeTenantPolicyUser(UserRole::STAFF);

    expect((new TenantPolicy)->viewAny($user))->toBeFalse();
});

test('super admin can view tenant', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->view($user, $tenant))->toBeTrue();
});

test('tenant owner is denied view tenant', function () {
    $user = makeTenantPolicyUser(UserRole::TENANT_OWNER);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->view($user, $tenant))->toBeFalse();
});

test('super admin can update tenant settings', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->update($user, $tenant))->toBeTrue();
});

test('staff is denied update tenant settings', function () {
    $user = makeTenantPolicyUser(UserRole::STAFF);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->update($user, $tenant))->toBeFalse();
});

test('super admin can create tenants', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);

    expect((new TenantPolicy)->create($user))->toBeTrue();
});

test('tenant owner is denied create tenants', function () {
    $user = makeTenantPolicyUser(UserRole::TENANT_OWNER);

    expect((new TenantPolicy)->create($user))->toBeFalse();
});

test('super admin can delete tenant', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeTrue();
});

test('staff is denied delete tenant', function () {
    $user = makeTenantPolicyUser(UserRole::STAFF);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeFalse();
});

test('super admin can restore tenant', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->restore($user, $tenant))->toBeTrue();
});

test('tenant owner is denied restore tenant', function () {
    $user = makeTenantPolicyUser(UserRole::TENANT_OWNER);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->restore($user, $tenant))->toBeFalse();
});

test('super admin can forceDelete tenant', function () {
    $user = makeTenantPolicyUser(UserRole::SUPER_ADMIN);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->forceDelete($user, $tenant))->toBeTrue();
});

test('customer is denied forceDelete tenant', function () {
    $user = makeTenantPolicyUser(UserRole::CUSTOMER);
    $tenant = makeTenantPolicyTenant();

    expect((new TenantPolicy)->forceDelete($user, $tenant))->toBeFalse();
});
