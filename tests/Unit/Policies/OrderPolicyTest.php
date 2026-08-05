<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use Illuminate\Auth\Access\Response;

// ═══════════════════════════════════════════════════════════════════════════
// HELPER: Build a User mock with tenant IDs (memoized for ownsResource)
// ═══════════════════════════════════════════════════════════════════════════

function makeUserWithTenants(array $tenantIds, string $role = 'seller'): User
{
    $tenantIds = array_map(
        static fn (int|string $id): string => is_int($id)
            ? sprintf('00000000-0000-4000-8000-%012d', $id)
            : $id,
        $tenantIds,
    );
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = $role;

    // ownsResource() reads _memoized_tenant_ids directly (VC-AUDIT-005)
    $user->_memoized_tenant_ids = $tenantIds;

    // hasAnyTenant() calls $user->tenants()->exists()
    // We mock tenants() to return a HasMany-compatible mock
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

function makeOrder(int $tenantId, string $status = 'pending'): Order
{
    $order = Mockery::mock(Order::class)->makePartial();
    $order->tenant_id = sprintf('00000000-0000-4000-8000-%012d', $tenantId);
    $order->status = $status;
    $order->status_label = Order::KANBAN_STATUSES[$status] ?? ucfirst($status);

    return $order;
}

// ═══════════════════════════════════════════════════════════════════════════
// TENANT ISOLATION: Cross-tenant denial
// ═══════════════════════════════════════════════════════════════════════════

test('denies view for cross-tenant order', function () {
    $user = makeUserWithTenants([1]);
    $order = makeOrder(tenantId: 2);

    expect((new OrderPolicy)->view($user, $order))->toBeFalse();
});

test('denies update for cross-tenant order', function () {
    $user = makeUserWithTenants([1]);
    $order = makeOrder(tenantId: 2);

    expect((new OrderPolicy)->update($user, $order))->toBeFalse();
});

test('denies updateStatus for cross-tenant order', function () {
    $user = makeUserWithTenants([1]);
    $order = makeOrder(tenantId: 2);

    expect((new OrderPolicy)->updateStatus($user, $order))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// TENANT ISOLATION: Same-tenant access
// ═══════════════════════════════════════════════════════════════════════════

test('allows view for same-tenant order', function () {
    $user = makeUserWithTenants([5]);
    $order = makeOrder(tenantId: 5);

    expect((new OrderPolicy)->view($user, $order))->toBeTrue();
});

test('allows update for same-tenant order', function () {
    $user = makeUserWithTenants([5]);
    $order = makeOrder(tenantId: 5);

    expect((new OrderPolicy)->update($user, $order))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// EDGE: User with no tenants
// ═══════════════════════════════════════════════════════════════════════════

test('denies viewAny when user has no tenants', function () {
    $user = makeUserWithTenants([]);

    expect((new OrderPolicy)->viewAny($user))->toBeFalse();
});

test('allows viewAny when user has a tenant', function () {
    $user = makeUserWithTenants([5]);

    expect((new OrderPolicy)->viewAny($user))->toBeTrue();
});

test('denies view when user has no tenants', function () {
    $user = makeUserWithTenants([]);
    $order = makeOrder(tenantId: 1);

    expect((new OrderPolicy)->view($user, $order))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// BUSINESS RULES: Delete - protected statuses
// ═══════════════════════════════════════════════════════════════════════════

test('allows delete for pending same-tenant order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_PENDING);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
});

test('denies delete for processing order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_PROCESSING);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeFalse();
});

test('denies delete for delivered order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_DELIVERED);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result->allowed())->toBeFalse();
});

test('denies delete for cross-tenant order regardless of status', function () {
    $user = makeUserWithTenants([1]);
    $order = makeOrder(tenantId: 2, status: Order::STATUS_PENDING);

    $result = (new OrderPolicy)->delete($user, $order);

    expect($result->allowed())->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// BUSINESS RULES: Cancel - only pending/confirmed
// ═══════════════════════════════════════════════════════════════════════════

test('allows cancel for pending order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_PENDING);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeTrue();
});

test('allows cancel for confirmed order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_CONFIRMED);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeTrue();
});

test('denies cancel for processing order', function () {
    $user = makeUserWithTenants([10]);
    $order = makeOrder(tenantId: 10, status: Order::STATUS_PROCESSING);

    $result = (new OrderPolicy)->cancel($user, $order);

    expect($result->allowed())->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════
// FORCE DELETE: Requires admin + ownership
// ═══════════════════════════════════════════════════════════════════════════

test('allows forceDelete for super_admin on same tenant', function () {
    $user = makeUserWithTenants([10], role: 'super_admin');
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn(true);
    $order = makeOrder(tenantId: 10);

    expect((new OrderPolicy)->forceDelete($user, $order))->toBeTrue();
});

test('denies forceDelete for non-admin on same tenant', function () {
    $user = makeUserWithTenants([10], role: 'seller');
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn(false);
    $order = makeOrder(tenantId: 10);

    expect((new OrderPolicy)->forceDelete($user, $order))->toBeFalse();
});
