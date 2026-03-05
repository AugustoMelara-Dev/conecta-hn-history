<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Auth\Access\Response;

// Bootstrap Laravel app — needed for Filament::shouldReceive() in create() tests
// uses(Tests\TestCase::class);

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build a User mock with tenant IDs (reuses OrderPolicy pattern)
// ═══════════════════════════════════════════════════════════════════════════

function makeUserForProduct(array $tenantIds, string $role = 'seller'): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = $role;
    $user->_memoized_tenant_ids = $tenantIds;

    $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $relation->shouldReceive('pluck')->with('id')->andReturn(collect($tenantIds));
    $relation->shouldReceive('exists')->andReturn(count($tenantIds) > 0);
    $relation->shouldReceive('first')->andReturn(
        count($tenantIds) > 0
            ? (object) ['id' => $tenantIds[0]]
            : null
    );
    $user->shouldReceive('tenants')->andReturn($relation);

    return $user;
}

function makeProduct(int $tenantId): Product
{
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = $tenantId;

    return $product;
}

// ═══════════════════════════════════════════════════════════════════════════
// TENANT ISOLATION: Cross-tenant denial
// ═══════════════════════════════════════════════════════════════════════════

test('denies view for cross-tenant product', function () {
    $user = makeUserForProduct([1]);
    $product = makeProduct(tenantId: 2);

    expect((new ProductPolicy())->view($user, $product))->toBeFalse();
});

test('denies update for cross-tenant product', function () {
    $user = makeUserForProduct([1]);
    $product = makeProduct(tenantId: 2);

    expect((new ProductPolicy())->update($user, $product))->toBeFalse();
});

test('denies delete for cross-tenant product', function () {
    $user = makeUserForProduct([1]);
    $product = makeProduct(tenantId: 2);

    expect((new ProductPolicy())->delete($user, $product))->toBeFalse();
});

test('denies restore for cross-tenant product', function () {
    $user = makeUserForProduct([1]);
    $product = makeProduct(tenantId: 2);

    expect((new ProductPolicy())->restore($user, $product))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT ISOLATION: Same-tenant access
// ═══════════════════════════════════════════════════════════════════════════

test('allows view for same-tenant product', function () {
    $user = makeUserForProduct([5]);
    $product = makeProduct(tenantId: 5);

    expect((new ProductPolicy())->view($user, $product))->toBeTrue();
});

test('allows update for same-tenant product', function () {
    $user = makeUserForProduct([5]);
    $product = makeProduct(tenantId: 5);

    expect((new ProductPolicy())->update($user, $product))->toBeTrue();
});

test('allows delete for same-tenant product', function () {
    $user = makeUserForProduct([5]);
    $product = makeProduct(tenantId: 5);

    expect((new ProductPolicy())->delete($user, $product))->toBeTrue();
});

test('allows restore for same-tenant product', function () {
    $user = makeUserForProduct([5]);
    $product = makeProduct(tenantId: 5);

    expect((new ProductPolicy())->restore($user, $product))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// EDGE: User with no tenants
// ═══════════════════════════════════════════════════════════════════════════

test('denies viewAny when user has no tenants', function () {
    $user = makeUserForProduct([]);

    expect((new ProductPolicy())->viewAny($user))->toBeFalse();
});

test('allows viewAny when user has a tenant', function () {
    $user = makeUserForProduct([3]);

    expect((new ProductPolicy())->viewAny($user))->toBeTrue();
});

test('denies view when user has no tenants', function () {
    $user = makeUserForProduct([]);
    $product = makeProduct(tenantId: 1);

    expect((new ProductPolicy())->view($user, $product))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// PLAN LIMITS: create() — Pro vs Free
//
// NOTE: In test context (no Filament panel), Filament::getTenant() returns null,
// so the policy falls through to $user->tenants()->first().
// We control the tenant via the mock returned by tenants()->first().
// ═══════════════════════════════════════════════════════════════════════════

function makeUserForCreate(bool $isPro, int $productCount, array $tenantIds = [10]): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = 'seller';
    $user->_memoized_tenant_ids = $tenantIds;

    // Build a tenant mock with is_pro and products() support
    $productsRelation = Mockery::mock();
    $productsRelation->shouldReceive('count')->andReturn($productCount);

    $tenant = Mockery::mock();
    $tenant->is_pro = $isPro;
    $tenant->shouldReceive('products')->andReturn($productsRelation);

    $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $relation->shouldReceive('pluck')->with('id')->andReturn(collect($tenantIds));
    $relation->shouldReceive('exists')->andReturn(count($tenantIds) > 0);
    $relation->shouldReceive('first')->andReturn(
        count($tenantIds) > 0 ? $tenant : null
    );
    $user->shouldReceive('tenants')->andReturn($relation);

    return $user;
}

test('create allows PRO tenant with unlimited products', function () {
    $user = makeUserForCreate(isPro: true, productCount: 999);

    $result = (new ProductPolicy())->create($user);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
});

test('create allows FREE tenant under 10 products', function () {
    $user = makeUserForCreate(isPro: false, productCount: 5);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeTrue();
});

test('create denies FREE tenant at 10 products', function () {
    $user = makeUserForCreate(isPro: false, productCount: 10);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('límite de 10 productos');
});

test('create denies user with no tenant', function () {
    $user = makeUserForCreate(isPro: false, productCount: 0, tenantIds: []);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('negocio');
});


// ═══════════════════════════════════════════════════════════════════════════
// FORCE DELETE: Requires admin + ownership
// ═══════════════════════════════════════════════════════════════════════════

test('allows forceDelete for super_admin owning product', function () {
    $user = makeUserForProduct([10], role: 'super_admin');
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn(true);
    $product = makeProduct(tenantId: 10);

    expect((new ProductPolicy())->forceDelete($user, $product))->toBeTrue();
});

test('denies forceDelete for non-admin owning product', function () {
    $user = makeUserForProduct([10], role: 'seller');
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn(false);
    $product = makeProduct(tenantId: 10);

    expect((new ProductPolicy())->forceDelete($user, $product))->toBeFalse();
});

test('denies forceDelete for admin on cross-tenant product', function () {
    $user = makeUserForProduct([1], role: 'super_admin');
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn(true);
    $product = makeProduct(tenantId: 2);

    expect((new ProductPolicy())->forceDelete($user, $product))->toBeFalse();
});
