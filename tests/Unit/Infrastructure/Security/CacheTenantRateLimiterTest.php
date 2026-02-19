<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Infrastructure\Security\CacheTenantRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Mockery;

uses(\Tests\TestCase::class);

test('ensureNotExceeded allows request within limit', function () {
    $mockLimiter = Mockery::mock(RateLimiter::class);
    $key = 'test-action:tenant:tenant-123';
    
    $mockLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($key, 10)
        ->andReturn(false);
        
    $mockLimiter->shouldReceive('hit')
        ->once()
        ->with($key, 60);
        
    $rateLimiter = new CacheTenantRateLimiter($mockLimiter);
    
    $rateLimiter->ensureNotExceeded('test-action', 'tenant-123', 10, 1);
});

test('ensureNotExceeded throws exception when limit exceeded', function () {
    $mockLimiter = Mockery::mock(RateLimiter::class);
    $key = 'test-action:tenant:tenant-123';
    
    $mockLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($key, 10)
        ->andReturn(true);
        
    $mockLimiter->shouldReceive('availableIn')
        ->once()
        ->with($key)
        ->andReturn(30);
        
    $rateLimiter = new CacheTenantRateLimiter($mockLimiter);
    
    expect(fn() => $rateLimiter->ensureNotExceeded('test-action', 'tenant-123', 10, 1))
        ->toThrow(ThrottleRequestsException::class);
});

test('ensureNotExceeded enforces tenant isolation', function () {
    // This test simulates a scenario where one tenant is blocked but another is not using mocked interactions
    $mockLimiter = Mockery::mock(RateLimiter::class);
    $tenantA = 'tenant-A';
    $tenantB = 'tenant-B';
    $action = 'test-action';
    
    $keyA = "{$action}:tenant:{$tenantA}";
    $keyB = "{$action}:tenant:{$tenantB}";
    
    // Tenant A is blocked
    $mockLimiter->shouldReceive('tooManyAttempts')
        ->with($keyA, 10)
        ->andReturn(true);
        
    $mockLimiter->shouldReceive('availableIn')
        ->with($keyA)
        ->andReturn(60);
        
    // Tenant B is NOT blocked
    $mockLimiter->shouldReceive('tooManyAttempts')
        ->with($keyB, 10)
        ->andReturn(false);
        
    $mockLimiter->shouldReceive('hit')
        ->with($keyB, 60);
        
    $rateLimiter = new CacheTenantRateLimiter($mockLimiter);
    
    // Check Tenant A
    try {
        $rateLimiter->ensureNotExceeded($action, $tenantA, 10, 1);
        $this->fail('Tenant A should be throttled');
    } catch (ThrottleRequestsException $e) {
        expect(true)->toBeTrue();
    }
    
    // Check Tenant B - should succeed
    $rateLimiter->ensureNotExceeded($action, $tenantB, 10, 1);
});
