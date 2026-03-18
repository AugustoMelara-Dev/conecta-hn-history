<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build User mock with role support for hasRole()
// ═══════════════════════════════════════════════════════════════════════════

function makeUserWithRole(string $role): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = $role;

    // hasRole accepts string or array — simulate membership check
    $user->shouldReceive('hasRole')->andReturnUsing(function ($roles) use ($role) {
        if (is_array($roles)) {
            return in_array($role, $roles, true);
        }

        return $role === $roles;
    });

    return $user;
}

function makeTenantMock(string $tenantId = '00000000-0000-0000-0000-000000000001'): Tenant
{
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = $tenantId;

    return $tenant;
}

// ═══════════════════════════════════════════════════════════════════════════
// ROLE MATRIX: viewAny — Super Admin + Moderator allowed
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can viewAny tenants', function () {
    $user = makeUserWithRole('Super Admin');

    expect((new TenantPolicy)->viewAny($user))->toBeTrue();
});

test('Moderator can viewAny tenants', function () {
    $user = makeUserWithRole('Moderator');

    expect((new TenantPolicy)->viewAny($user))->toBeTrue();
});

test('seller DENIED viewAny tenants', function () {
    $user = makeUserWithRole('seller');

    expect((new TenantPolicy)->viewAny($user))->toBeFalse();
});

test('staff DENIED viewAny tenants', function () {
    $user = makeUserWithRole('staff');

    expect((new TenantPolicy)->viewAny($user))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// ROLE MATRIX: view — Super Admin + Moderator allowed
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can view tenant', function () {
    $user = makeUserWithRole('Super Admin');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->view($user, $tenant))->toBeTrue();
});

test('Moderator can view tenant', function () {
    $user = makeUserWithRole('Moderator');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->view($user, $tenant))->toBeTrue();
});

test('seller DENIED view tenant', function () {
    $user = makeUserWithRole('seller');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->view($user, $tenant))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// ROLE MATRIX: update — Super Admin + Moderator allowed
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can update tenant settings', function () {
    $user = makeUserWithRole('Super Admin');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->update($user, $tenant))->toBeTrue();
});

test('Moderator can update tenant settings', function () {
    $user = makeUserWithRole('Moderator');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->update($user, $tenant))->toBeTrue();
});

test('seller DENIED update tenant settings', function () {
    $user = makeUserWithRole('seller');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->update($user, $tenant))->toBeFalse();
});

test('staff DENIED update tenant settings (self-management防止)', function () {
    $user = makeUserWithRole('staff');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->update($user, $tenant))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SAFETY: create — Super Admin ONLY
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can create tenants', function () {
    $user = makeUserWithRole('Super Admin');

    expect((new TenantPolicy)->create($user))->toBeTrue();
});

test('Moderator DENIED create tenants', function () {
    $user = makeUserWithRole('Moderator');

    expect((new TenantPolicy)->create($user))->toBeFalse();
});

test('seller DENIED create tenants', function () {
    $user = makeUserWithRole('seller');

    expect((new TenantPolicy)->create($user))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SAFETY: delete — Super Admin ONLY (suicide prevention)
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can delete tenant', function () {
    $user = makeUserWithRole('Super Admin');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeTrue();
});

test('Moderator DENIED delete tenant', function () {
    $user = makeUserWithRole('Moderator');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeFalse();
});

test('seller DENIED delete tenant (data loss prevention)', function () {
    $user = makeUserWithRole('seller');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeFalse();
});

test('staff DENIED delete tenant (suicide prevention)', function () {
    $user = makeUserWithRole('staff');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->delete($user, $tenant))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SAFETY: restore — Super Admin ONLY
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can restore tenant', function () {
    $user = makeUserWithRole('Super Admin');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->restore($user, $tenant))->toBeTrue();
});

test('Moderator DENIED restore tenant', function () {
    $user = makeUserWithRole('Moderator');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->restore($user, $tenant))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT SAFETY: forceDelete — Super Admin ONLY (destructive)
// ═══════════════════════════════════════════════════════════════════════════

test('Super Admin can forceDelete tenant', function () {
    $user = makeUserWithRole('Super Admin');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->forceDelete($user, $tenant))->toBeTrue();
});

test('Moderator DENIED forceDelete tenant', function () {
    $user = makeUserWithRole('Moderator');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->forceDelete($user, $tenant))->toBeFalse();
});

test('seller DENIED forceDelete tenant', function () {
    $user = makeUserWithRole('seller');
    $tenant = makeTenantMock();

    expect((new TenantPolicy)->forceDelete($user, $tenant))->toBeFalse();
});
