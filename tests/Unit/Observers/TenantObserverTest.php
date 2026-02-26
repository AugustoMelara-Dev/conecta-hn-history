<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\TenantObserver;
use App\Services\TenantCacheManager;
use Illuminate\Support\Facades\Log;

// ═══════════════════════════════════════════════════════════════════════════
// CREATED EVENT: Role Assignment
// ═══════════════════════════════════════════════════════════════════════════

test('created assigns TENANT_OWNER role to user who is not already owner', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = UserRole::CUSTOMER;
    $user->shouldReceive('update')
        ->once()
        ->with(['role' => UserRole::TENANT_OWNER]);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('getAttribute')->with('user')->andReturn($user);

    $observer = new TenantObserver();
    $observer->created($tenant);
});

test('created does not reassign role if user is already TENANT_OWNER', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->role = UserRole::TENANT_OWNER;
    $user->shouldNotReceive('update');

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('getAttribute')->with('user')->andReturn($user);

    $observer = new TenantObserver();
    $observer->created($tenant);
});

test('created handles tenant without user gracefully', function () {
    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->shouldReceive('getAttribute')->with('user')->andReturn(null);

    $observer = new TenantObserver();

    // Should not throw
    $observer->created($tenant);
    expect(true)->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// UPDATED EVENT: Cache Rotation
// ═══════════════════════════════════════════════════════════════════════════

test('updated rotates tenant cache version', function () {
    $mock = Mockery::mock(TenantCacheManager::class);
    $mock->shouldReceive('rotateTenantVersion')
        ->once()
        ->with(42);
    app()->instance(TenantCacheManager::class, $mock);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = 42;

    $observer = new TenantObserver();
    $observer->updated($tenant);
});

test('updated logs warning when cache rotation fails', function () {
    $mock = Mockery::mock(TenantCacheManager::class);
    $mock->shouldReceive('rotateTenantVersion')
        ->andThrow(new \RuntimeException('Redis down'));
    app()->instance(TenantCacheManager::class, $mock);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Cache invalidation failed for tenant 42'));

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = 42;

    $observer = new TenantObserver();
    $observer->updated($tenant);
});

// ═══════════════════════════════════════════════════════════════════════════
// DELETED EVENT: Cache Cleanup
// ═══════════════════════════════════════════════════════════════════════════

test('deleted forgets tenant cache version', function () {
    $mock = Mockery::mock(TenantCacheManager::class);
    $mock->shouldReceive('forgetVersion')
        ->once()
        ->with(99);
    app()->instance(TenantCacheManager::class, $mock);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = 99;

    $observer = new TenantObserver();
    $observer->deleted($tenant);
});

// ═══════════════════════════════════════════════════════════════════════════
// RESTORED EVENT: Cache Rotation
// ═══════════════════════════════════════════════════════════════════════════

test('restored rotates tenant cache version', function () {
    $mock = Mockery::mock(TenantCacheManager::class);
    $mock->shouldReceive('rotateTenantVersion')
        ->once()
        ->with(7);
    app()->instance(TenantCacheManager::class, $mock);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = 7;

    $observer = new TenantObserver();
    $observer->restored($tenant);
});

// ═══════════════════════════════════════════════════════════════════════════
// FORCE DELETED EVENT: Cache Cleanup
// ═══════════════════════════════════════════════════════════════════════════

test('forceDeleted forgets tenant cache version', function () {
    $mock = Mockery::mock(TenantCacheManager::class);
    $mock->shouldReceive('forgetVersion')
        ->once()
        ->with(7);
    app()->instance(TenantCacheManager::class, $mock);

    $tenant = Mockery::mock(Tenant::class)->makePartial();
    $tenant->id = 7;

    $observer = new TenantObserver();
    $observer->forceDeleted($tenant);
});
