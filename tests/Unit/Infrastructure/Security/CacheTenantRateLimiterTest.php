<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\CacheTenantRateLimiter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

test('ensureNotExceeded allows request within limit', function () {
    [$limiter, $rateLimiter] = makeTenantRateLimiter();

    $rateLimiter->ensureNotExceeded('test-action', 'tenant-123', 10, 1);

    expect($limiter->tooManyAttempts('test-action:tenant:tenant-123', 10))->toBeFalse();
});

test('ensureNotExceeded throws exception when limit exceeded', function () {
    [$limiter, $rateLimiter] = makeTenantRateLimiter();
    $key = 'test-action:tenant:tenant-123';

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $limiter->hit($key, 60);
    }

    expect(fn () => $rateLimiter->ensureNotExceeded('test-action', 'tenant-123', 10, 1))
        ->toThrow(ThrottleRequestsException::class);
});

test('ensureNotExceeded enforces tenant isolation', function () {
    [$limiter, $rateLimiter] = makeTenantRateLimiter();

    $tenantA = 'tenant-A';
    $tenantB = 'tenant-B';
    $action = 'test-action';

    $keyA = "{$action}:tenant:{$tenantA}";
    $keyB = "{$action}:tenant:{$tenantB}";

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $limiter->hit($keyA, 60);
    }

    expect(fn () => $rateLimiter->ensureNotExceeded($action, $tenantA, 10, 1))
        ->toThrow(ThrottleRequestsException::class);

    $rateLimiter->ensureNotExceeded($action, $tenantB, 10, 1);

    expect($limiter->tooManyAttempts($keyA, 10))->toBeTrue();
    expect($limiter->tooManyAttempts($keyB, 10))->toBeFalse();
});

/**
 * @return array{0: RateLimiter, 1: CacheTenantRateLimiter}
 */
function makeTenantRateLimiter(): array
{
    $limiter = new RateLimiter(new Repository(new ArrayStore));

    return [$limiter, new CacheTenantRateLimiter($limiter)];
}
