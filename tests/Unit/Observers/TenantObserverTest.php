<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\TenantObserver;
use App\Services\TenantCacheManager;
use Illuminate\Support\Facades\Log;

final class RecordingObserverUser extends User
{
    /** @var list<array<string, mixed>> */
    public array $updatedPayloads = [];

    public function __construct(?UserRole $role = null)
    {
        parent::__construct();

        if ($role instanceof UserRole) {
            $this->forceFill(['role' => $role]);
        }
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        $this->updatedPayloads[] = $attributes;
        $this->forceFill($attributes);

        return true;
    }
}

final class RecordingTenantCacheManager extends TenantCacheManager
{
    public ?string $rotatedTenantId = null;

    public ?string $forgottenTenantId = null;

    public ?RuntimeException $rotateFailure = null;

    public function rotateTenantVersion(int|string $tenantId): int
    {
        if ($this->rotateFailure instanceof RuntimeException) {
            throw $this->rotateFailure;
        }

        $this->rotatedTenantId = (string) $tenantId;

        return 1;
    }

    public function forgetVersion(int|string $tenantId): bool
    {
        $this->forgottenTenantId = (string) $tenantId;

        return true;
    }
}

function makeObservedTenant(int|string $tenantId, ?User $user = null): Tenant
{
    $tenant = new Tenant;
    $tenant->forceFill(['id' => (string) $tenantId]);

    if ($user instanceof User) {
        $tenant->setRelation('user', $user);
    }

    return $tenant;
}

test('created assigns TENANT_OWNER role to user who is not already owner', function () {
    $user = new RecordingObserverUser(UserRole::CUSTOMER);
    $tenant = makeObservedTenant('42', $user);

    $observer = new TenantObserver;
    $observer->created($tenant);

    expect($user->updatedPayloads)->toHaveCount(1);
    expect($user->updatedPayloads[0]['role'])->toBe(UserRole::TENANT_OWNER);
    expect($user->role)->toBe(UserRole::TENANT_OWNER);
});

test('created does not reassign role if user is already TENANT_OWNER', function () {
    $user = new RecordingObserverUser(UserRole::TENANT_OWNER);
    $tenant = makeObservedTenant('42', $user);

    $observer = new TenantObserver;
    $observer->created($tenant);

    expect($user->updatedPayloads)->toBeEmpty();
});

test('created handles tenant without user gracefully', function () {
    $tenant = makeObservedTenant('42');

    $observer = new TenantObserver;
    $observer->created($tenant);

    expect(true)->toBeTrue();
});

test('updated rotates tenant cache version', function () {
    $cacheManager = new RecordingTenantCacheManager;
    app()->instance(TenantCacheManager::class, $cacheManager);

    $tenant = makeObservedTenant('42');

    $observer = new TenantObserver;
    $observer->updated($tenant);

    expect($cacheManager->rotatedTenantId)->toBe('42');
});

test('updated logs warning when cache rotation fails', function () {
    $cacheManager = new RecordingTenantCacheManager;
    $cacheManager->rotateFailure = new RuntimeException('Redis down');
    app()->instance(TenantCacheManager::class, $cacheManager);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'Cache invalidation failed for tenant 42'));

    $tenant = makeObservedTenant('42');

    $observer = new TenantObserver;
    $observer->updated($tenant);
});

test('deleted forgets tenant cache version', function () {
    $cacheManager = new RecordingTenantCacheManager;
    app()->instance(TenantCacheManager::class, $cacheManager);

    $tenant = makeObservedTenant('99');

    $observer = new TenantObserver;
    $observer->deleted($tenant);

    expect($cacheManager->forgottenTenantId)->toBe('99');
});

test('restored rotates tenant cache version', function () {
    $cacheManager = new RecordingTenantCacheManager;
    app()->instance(TenantCacheManager::class, $cacheManager);

    $tenant = makeObservedTenant('7');

    $observer = new TenantObserver;
    $observer->restored($tenant);

    expect($cacheManager->rotatedTenantId)->toBe('7');
});

test('forceDeleted forgets tenant cache version', function () {
    $cacheManager = new RecordingTenantCacheManager;
    app()->instance(TenantCacheManager::class, $cacheManager);

    $tenant = makeObservedTenant('7');

    $observer = new TenantObserver;
    $observer->forceDeleted($tenant);

    expect($cacheManager->forgottenTenantId)->toBe('7');
});
