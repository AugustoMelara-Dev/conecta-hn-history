<?php

declare(strict_types=1);

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Domain\Access\Enums\Permission;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\OrderPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery\Expectation;
use Mockery\MockInterface;

function normalizeOrderTenantId(int|string $tenantId): string
{
    if (is_string($tenantId) && str_contains($tenantId, '-')) {
        return $tenantId;
    }

    return '00000000-0000-0000-0000-0000000000'.str_pad((string) $tenantId, 2, '0', STR_PAD_LEFT);
}

function bindOrderPolicyGuard(bool $allows = true): void
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
}

function fakeOrderTenantContext(?Tenant $tenant): void
{
    Filament::shouldReceive('getTenant')->andReturn($tenant);
}

function makeOrderPolicyUser(UserRole $role = UserRole::TENANT_OWNER, ?string $id = null): User
{
    $user = new User;
    $user->forceFill([
        'id' => $id ?? normalizeOrderTenantId(random_int(1, 99)),
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'role' => $role,
    ]);

    return $user;
}

function makeOrderPolicyTenant(int|string $tenantId, ?string $ownerId = null): Tenant
{
    $tenant = new Tenant;
    $tenant->forceFill([
        'id' => normalizeOrderTenantId($tenantId),
        'user_id' => $ownerId,
    ]);

    return $tenant;
}

function makeOrderPolicyOrder(int|string $tenantId, string $status = Order::STATUS_PENDING): Order
{
    $order = new Order;
    $order->forceFill([
        'tenant_id' => normalizeOrderTenantId($tenantId),
        'status' => $status,
    ]);

    return $order;
}

/**
 * @return User&MockInterface
 */
function makeOrderUserWithoutTenants(UserRole $role = UserRole::TENANT_OWNER, ?string $id = null): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill([
        'id' => $id ?? normalizeOrderTenantId(random_int(100, 199)),
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'role' => $role,
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

test('denies view for cross-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 2);
    $tenant = makeOrderPolicyTenant(tenantId: 1);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->view($user, $order))->toBeFalse();
});

test('denies update for cross-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 2);
    $tenant = makeOrderPolicyTenant(tenantId: 1);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->update($user, $order))->toBeFalse();
});

test('denies updateStatus for cross-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 2);
    $tenant = makeOrderPolicyTenant(tenantId: 1);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->updateStatus($user, $order))->toBeFalse();
});

test('allows view for same-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 5);
    $tenant = makeOrderPolicyTenant(tenantId: 5);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->view($user, $order))->toBeTrue();
});

test('allows update for same-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 5);
    $tenant = makeOrderPolicyTenant(tenantId: 5);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->update($user, $order))->toBeTrue();
});

test('denies viewAny when user has no tenants', function () {
    bindOrderPolicyGuard();

    $user = makeOrderUserWithoutTenants();
    fakeOrderTenantContext(null);

    expect((new OrderPolicy)->viewAny($user))->toBeFalse();
});

test('allows viewAny when there is an active tenant context', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $tenant = makeOrderPolicyTenant(tenantId: 5);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->viewAny($user))->toBeTrue();
});

test('denies view when user has no tenants', function () {
    bindOrderPolicyGuard();

    $user = makeOrderUserWithoutTenants();
    $order = makeOrderPolicyOrder(tenantId: 1);
    fakeOrderTenantContext(null);

    expect((new OrderPolicy)->view($user, $order))->toBeFalse();
});

test('allows delete for pending same-tenant order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_PENDING);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
});

test('denies delete for processing order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_PROCESSING);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeFalse();
});

test('denies delete for delivered order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_DELIVERED);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result->allowed())->toBeFalse();
});

test('denies delete for cross-tenant order regardless of status', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 2, status: Order::STATUS_PENDING);
    $tenant = makeOrderPolicyTenant(tenantId: 1);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result->allowed())->toBeFalse();
});

test('allows cancel for pending order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_PENDING);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeTrue();
});

test('allows cancel for confirmed order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_CONFIRMED);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeTrue();
});

test('denies cancel for processing order', function () {
    bindOrderPolicyGuard();

    $user = makeOrderPolicyUser();
    $order = makeOrderPolicyOrder(tenantId: 10, status: Order::STATUS_PROCESSING);
    $tenant = makeOrderPolicyTenant(tenantId: 10);
    fakeOrderTenantContext($tenant);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeFalse();
});

test('allows forceDelete for tenant owner in the same tenant', function () {
    bindOrderPolicyGuard();

    $userId = normalizeOrderTenantId(123);
    $user = makeOrderPolicyUser(role: UserRole::SUPER_ADMIN, id: $userId);
    $order = makeOrderPolicyOrder(tenantId: 10);
    $tenant = makeOrderPolicyTenant(tenantId: 10, ownerId: $userId);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->forceDelete($user, $order))->toBeTrue();
});

test('denies forceDelete for same-tenant user without ownership', function () {
    bindOrderPolicyGuard();

    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill([
        'id' => normalizeOrderTenantId(777),
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'role' => UserRole::SUPER_ADMIN,
    ]);

    /** @var HasMany&MockInterface $tenantMemberships */
    $tenantMemberships = Mockery::mock(HasMany::class);
    /** @var Expectation $tenantMatch */
    $tenantMatch = $tenantMemberships->shouldReceive('where');
    $tenantMatch->with('tenant_id', normalizeOrderTenantId(10))->andReturnSelf();
    /** @var Expectation $roleMatch */
    $roleMatch = $tenantMemberships->shouldReceive('where');
    $roleMatch->with('role', 'owner')->andReturnSelf();
    /** @var Expectation $acceptedAt */
    $acceptedAt = $tenantMemberships->shouldReceive('whereNotNull');
    $acceptedAt->with('accepted_at')->andReturnSelf();
    /** @var Expectation $existsExpectation */
    $existsExpectation = $tenantMemberships->shouldReceive('exists');
    $existsExpectation->andReturn(false);
    $user->shouldReceive('tenantMemberships')->andReturn($tenantMemberships);

    $order = makeOrderPolicyOrder(tenantId: 10);
    $tenant = makeOrderPolicyTenant(tenantId: 10, ownerId: normalizeOrderTenantId(123));
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->forceDelete($user, $order))->toBeFalse();
});

test('denies forceDelete for cross-tenant order', function () {
    bindOrderPolicyGuard();

    $userId = normalizeOrderTenantId(123);
    $user = makeOrderPolicyUser(role: UserRole::SUPER_ADMIN, id: $userId);
    $order = makeOrderPolicyOrder(tenantId: 2);
    $tenant = makeOrderPolicyTenant(tenantId: 1, ownerId: $userId);
    fakeOrderTenantContext($tenant);

    expect((new OrderPolicy)->forceDelete($user, $order))->toBeFalse();
});
