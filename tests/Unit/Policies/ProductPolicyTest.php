<?php

declare(strict_types=1);

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Access\Response;

function normalizeProductTenantId(int|string $tenantId): string
{
    if (is_string($tenantId) && str_contains($tenantId, '-')) {
        return $tenantId;
    }

    return '00000000-0000-0000-0000-0000000000'.str_pad((string) $tenantId, 2, '0', STR_PAD_LEFT);
}

function bindProductPolicyServices(): void
{
    $guard = Mockery::mock(TenantPermissionGuardInterface::class);
    $guard->shouldReceive('allows')->andReturn(true);
    app()->instance(TenantPermissionGuardInterface::class, $guard);

    $settings = new class
    {
        public int $free_plan_product_limit = 10;

        public int $pro_plan_product_limit = 1000;
    };

    app()->instance(GeneralSettings::class, $settings);
}

function makeUserForProduct(array $tenantIds, string $role = 'seller'): User
{
    $normalizedTenantIds = array_map(fn ($id) => normalizeProductTenantId($id), $tenantIds);

    $user = Mockery::mock(User::class)->makePartial();
    $user->role = $role;
    $user->_memoized_tenant_ids = $normalizedTenantIds;

    $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $relation->shouldReceive('pluck')->with('id')->andReturn(collect($normalizedTenantIds));
    $relation->shouldReceive('exists')->andReturn(count($normalizedTenantIds) > 0);
    $relation->shouldReceive('first')->andReturn(
        count($normalizedTenantIds) > 0
            ? (object) ['id' => $normalizedTenantIds[0]]
            : null
    );
    $user->shouldReceive('tenants')->andReturn($relation);

    return $user;
}

function makeProduct(int $tenantId): Product
{
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = normalizeProductTenantId($tenantId);

    return $product;
}

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

function makeUserForCreate(bool $isPro, int $productCount, array $tenantIds = [10]): User
{
    $normalizedTenantIds = array_map(fn ($id) => normalizeProductTenantId($id), $tenantIds);

    $user = Mockery::mock(User::class)->makePartial();
    $user->role = 'seller';
    $user->_memoized_tenant_ids = $normalizedTenantIds;

    $productsRelation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $productsRelation->shouldReceive('count')->andReturn($productCount);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = $normalizedTenantIds[0] ?? null;
    $tenant->forceFill([
        'plan_type' => $isPro ? TenantPlan::PRO : TenantPlan::SEMILLA,
        'subscription_status' => 'active',
        'subscription_ends_at' => null,
    ]);
    $tenant->shouldReceive('products')->andReturn($productsRelation);

    $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $relation->shouldReceive('pluck')->with('id')->andReturn(collect($normalizedTenantIds));
    $relation->shouldReceive('exists')->andReturn(count($normalizedTenantIds) > 0);
    $relation->shouldReceive('first')->andReturn(
        count($normalizedTenantIds) > 0 ? $tenant : null
    );
    $user->shouldReceive('tenants')->andReturn($relation);

    return $user;
}

test('create allows PRO tenant with unlimited products', function () {
    bindProductPolicyServices();
    $user = makeUserForCreate(isPro: true, productCount: 999);

    $result = (new ProductPolicy())->create($user);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
});

test('create allows FREE tenant under 10 products', function () {
    bindProductPolicyServices();
    $user = makeUserForCreate(isPro: false, productCount: 5);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeTrue();
});

test('create denies FREE tenant at 10 products', function () {
    bindProductPolicyServices();
    $user = makeUserForCreate(isPro: false, productCount: 10);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('limite de 10 productos');
});

test('create denies user with no tenant', function () {
    $user = makeUserForCreate(isPro: false, productCount: 0, tenantIds: []);

    $result = (new ProductPolicy())->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('negocio');
});

test('allows forceDelete for super_admin owning product', function () {
    bindProductPolicyServices();

    $tenantId = normalizeProductTenantId(10);
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = $tenantId;
    $tenant->user_id = '00000000-0000-0000-0000-000000000123';

    $relation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    $relation->shouldReceive('pluck')->with('id')->andReturn(collect([$tenantId]));
    $relation->shouldReceive('exists')->andReturn(true);
    $relation->shouldReceive('first')->andReturn($tenant);

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = '00000000-0000-0000-0000-000000000123';
    $user->role = 'super_admin';
    $user->_memoized_tenant_ids = [$tenantId];
    $user->shouldReceive('tenants')->andReturn($relation);
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
