<?php

declare(strict_types=1);

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Settings\GeneralSettings;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery\Expectation;
use Mockery\MockInterface;

function normalizeProductTenantId(int|string $tenantId): string
{
    if (is_string($tenantId) && str_contains($tenantId, '-')) {
        return $tenantId;
    }

    return '00000000-0000-0000-0000-0000000000'.str_pad((string) $tenantId, 2, '0', STR_PAD_LEFT);
}

function bindProductPolicyServices(bool $allows = true): void
{
    app()->instance(
        TenantPermissionGuardInterface::class,
        new class($allows) implements TenantPermissionGuardInterface
        {
            public function __construct(private bool $allows) {}

            public function allows(User $user, Tenant $tenant, Capability $capability, Permission $permission): bool
            {
                return $this->allows;
            }
        }
    );

    $settings = new class extends GeneralSettings
    {
        public function __construct() {}
    };
    $settings->free_plan_product_limit = 10;
    $settings->pro_plan_product_limit = 1000;

    app()->instance(GeneralSettings::class, $settings);
}

function fakeCurrentTenant(?Tenant $tenant): void
{
    Filament::shouldReceive('getTenant')->andReturn($tenant);
}

function makePolicyUser(UserRole $role = UserRole::CUSTOMER, ?string $id = null): User
{
    $user = new User;
    $user->forceFill([
        'id' => $id ?? normalizeProductTenantId(random_int(1, 99)),
        'role' => $role,
        'email' => fake()->safeEmail(),
        'name' => fake()->name(),
    ]);

    return $user;
}

function makePolicyProduct(int|string $tenantId): Product
{
    $product = new Product;
    $product->forceFill([
        'tenant_id' => normalizeProductTenantId($tenantId),
    ]);

    return $product;
}

function makePolicyTenant(
    int|string $tenantId,
    bool $isPro = false,
    int $productCount = 0,
    ?string $ownerId = null,
): Tenant {
    /** @var HasMany&MockInterface $productsRelation */
    $productsRelation = Mockery::mock(HasMany::class);
    /** @var Expectation $countExpectation */
    $countExpectation = $productsRelation->shouldReceive('count');
    $countExpectation->andReturn($productCount);

    $tenant = new class extends Tenant
    {
        private HasMany $productsRelation;

        public function setProductsRelation(HasMany $productsRelation): void
        {
            $this->productsRelation = $productsRelation;
        }

        public function products(): HasMany
        {
            return $this->productsRelation;
        }
    };

    $tenant->setProductsRelation($productsRelation);

    $tenant->forceFill([
        'id' => normalizeProductTenantId($tenantId),
        'user_id' => $ownerId,
        'plan_type' => $isPro ? TenantPlan::PRO : TenantPlan::SEMILLA,
        'subscription_status' => 'active',
        'subscription_ends_at' => null,
    ]);

    return $tenant;
}

/**
 * @return User&MockInterface
 */
function makeUserWithoutTenants(UserRole $role = UserRole::CUSTOMER, ?string $id = null): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill([
        'id' => $id ?? normalizeProductTenantId(random_int(100, 199)),
        'role' => $role,
        'email' => fake()->safeEmail(),
        'name' => fake()->name(),
    ]);

    /** @var HasMany&MockInterface $tenantsRelation */
    $tenantsRelation = Mockery::mock(HasMany::class);
    /** @var Expectation $tenantsPluck */
    $tenantsPluck = $tenantsRelation->shouldReceive('pluck');
    $tenantsPluck->with('id')->andReturn(collect());
    /** @var Expectation $tenantsFirst */
    $tenantsFirst = $tenantsRelation->shouldReceive('first');
    $tenantsFirst->andReturn(null);

    /** @var BelongsToMany&MockInterface $memberTenantsRelation */
    $memberTenantsRelation = Mockery::mock(BelongsToMany::class);
    /** @var Expectation $pivotExpectation */
    $pivotExpectation = $memberTenantsRelation->shouldReceive('wherePivotNotNull');
    $pivotExpectation->with('accepted_at')->andReturnSelf();
    /** @var Expectation $membersPluck */
    $membersPluck = $memberTenantsRelation->shouldReceive('pluck');
    $membersPluck->with('tenants.id')->andReturn(collect());
    /** @var Expectation $membersFirst */
    $membersFirst = $memberTenantsRelation->shouldReceive('first');
    $membersFirst->andReturn(null);

    $user->shouldReceive('tenants')->andReturn($tenantsRelation);
    $user->shouldReceive('memberTenants')->andReturn($memberTenantsRelation);

    return $user;
}

test('denies view for cross-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 2);
    $tenant = makePolicyTenant(tenantId: 1);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->view($user, $product))->toBeFalse();
});

test('denies update for cross-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 2);
    $tenant = makePolicyTenant(tenantId: 1);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->update($user, $product))->toBeFalse();
});

test('denies delete for cross-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 2);
    $tenant = makePolicyTenant(tenantId: 1);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->delete($user, $product))->toBeFalse();
});

test('denies restore for cross-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 2);
    $tenant = makePolicyTenant(tenantId: 1);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->restore($user, $product))->toBeFalse();
});

test('allows view for same-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 5);
    $tenant = makePolicyTenant(tenantId: 5);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->view($user, $product))->toBeTrue();
});

test('allows update for same-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 5);
    $tenant = makePolicyTenant(tenantId: 5);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->update($user, $product))->toBeTrue();
});

test('allows delete for same-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 5);
    $tenant = makePolicyTenant(tenantId: 5);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->delete($user, $product))->toBeTrue();
});

test('allows restore for same-tenant product', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $product = makePolicyProduct(tenantId: 5);
    $tenant = makePolicyTenant(tenantId: 5);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->restore($user, $product))->toBeTrue();
});

test('denies viewAny when user has no tenants', function () {
    bindProductPolicyServices();

    $user = makeUserWithoutTenants();
    fakeCurrentTenant(null);

    expect((new ProductPolicy)->viewAny($user))->toBeFalse();
});

test('allows viewAny when there is an active tenant context', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $tenant = makePolicyTenant(tenantId: 3);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->viewAny($user))->toBeTrue();
});

test('denies view when user has no tenants and no active tenant context', function () {
    bindProductPolicyServices();

    $user = makeUserWithoutTenants();
    $product = makePolicyProduct(tenantId: 1);
    fakeCurrentTenant(null);

    expect((new ProductPolicy)->view($user, $product))->toBeFalse();
});

test('create allows PRO tenant with unlimited products', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $tenant = makePolicyTenant(tenantId: 10, isPro: true, productCount: 999);
    fakeCurrentTenant($tenant);

    $result = (new ProductPolicy)->create($user);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
});

test('create allows FREE tenant under 10 products', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $tenant = makePolicyTenant(tenantId: 10, productCount: 5);
    fakeCurrentTenant($tenant);

    $result = (new ProductPolicy)->create($user);

    expect($result->allowed())->toBeTrue();
});

test('create denies FREE tenant at 10 products', function () {
    bindProductPolicyServices();

    $user = makePolicyUser();
    $tenant = makePolicyTenant(tenantId: 10, productCount: 10);
    fakeCurrentTenant($tenant);

    $result = (new ProductPolicy)->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('limite de 10 productos');
});

test('create denies user with no tenant', function () {
    bindProductPolicyServices();

    $user = makeUserWithoutTenants();
    fakeCurrentTenant(null);

    $result = (new ProductPolicy)->create($user);

    expect($result->allowed())->toBeFalse();
    expect($result->message())->toContain('negocio');
});

test('allows forceDelete for tenant owner in the same tenant', function () {
    bindProductPolicyServices();

    $userId = normalizeProductTenantId(123);
    $user = makePolicyUser(role: UserRole::SUPER_ADMIN, id: $userId);
    $product = makePolicyProduct(tenantId: 10);
    $tenant = makePolicyTenant(tenantId: 10, ownerId: $userId);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->forceDelete($user, $product))->toBeTrue();
});

test('denies forceDelete for same-tenant user that does not own the tenant', function () {
    bindProductPolicyServices();

    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill([
        'id' => normalizeProductTenantId(777),
        'role' => UserRole::SUPER_ADMIN,
        'email' => fake()->safeEmail(),
        'name' => fake()->name(),
    ]);

    /** @var HasMany&MockInterface $tenantMemberships */
    $tenantMemberships = Mockery::mock(HasMany::class);
    /** @var Expectation $membershipExists */
    $membershipExists = $tenantMemberships->shouldReceive('where');
    $membershipExists->with('tenant_id', normalizeProductTenantId(10))->andReturnSelf();
    /** @var Expectation $membershipRole */
    $membershipRole = $tenantMemberships->shouldReceive('where');
    $membershipRole->with('role', 'owner')->andReturnSelf();
    /** @var Expectation $acceptedAt */
    $acceptedAt = $tenantMemberships->shouldReceive('whereNotNull');
    $acceptedAt->with('accepted_at')->andReturnSelf();
    /** @var Expectation $existsExpectation */
    $existsExpectation = $tenantMemberships->shouldReceive('exists');
    $existsExpectation->andReturn(false);
    $user->shouldReceive('tenantMemberships')->andReturn($tenantMemberships);

    $product = makePolicyProduct(tenantId: 10);
    $tenant = makePolicyTenant(tenantId: 10, ownerId: normalizeProductTenantId(123));
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->forceDelete($user, $product))->toBeFalse();
});

test('denies forceDelete for cross-tenant product', function () {
    bindProductPolicyServices();

    $userId = normalizeProductTenantId(123);
    $user = makePolicyUser(role: UserRole::SUPER_ADMIN, id: $userId);
    $product = makePolicyProduct(tenantId: 2);
    $tenant = makePolicyTenant(tenantId: 1, ownerId: $userId);
    fakeCurrentTenant($tenant);

    expect((new ProductPolicy)->forceDelete($user, $product))->toBeFalse();
});
