<?php

declare(strict_types=1);

use App\Services\TenantCacheManager;
use Illuminate\Support\Facades\Cache;

function makeTenantCacheManager(): TenantCacheManager
{
    Cache::flush();

    return new TenantCacheManager;
}

describe('TenantCacheManager', function () {
    // ═══════════════════════════════════════════════════════════════════════════
    // MEMOIZATION PROPERTY TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('returns consistent version on consecutive calls (memoization)', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = makeTenantCacheManager();

        // First call
        $version1 = $manager->getTenantVersion($tenantId);

        // Second call - should return same value (memoized)
        $version2 = $manager->getTenantVersion($tenantId);

        expect($version1)->toBe($version2);
        expect($version1)->toBeInt();
    });

    it('returns integer type', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = makeTenantCacheManager();

        $version = $manager->getTenantVersion($tenantId);

        expect($version)->toBeInt();
    });

    it('accepts string tenant IDs (UUID support)', function () {
        $uuidTenantId = '550e8400-e29b-41d4-a716-446655440000';
        $manager = makeTenantCacheManager();

        $version = $manager->getTenantVersion($uuidTenantId);

        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    it('accepts integer tenant IDs', function () {
        $intTenantId = 12345;
        $manager = makeTenantCacheManager();

        $version = $manager->getTenantVersion($intTenantId);

        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // VERSION ROTATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('rotates version on rotateTenantVersion call', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = makeTenantCacheManager();

        $originalVersion = $manager->getTenantVersion($tenantId);

        // Wait 1 second to ensure new timestamp
        sleep(1);

        $newVersion = $manager->rotateTenantVersion($tenantId);

        expect($newVersion)->toBeGreaterThan($originalVersion);
        expect($newVersion)->toBeInt();
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // KEY GENERATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('generates versioned cache key correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $context = 'products_page_1';
        $manager = makeTenantCacheManager();

        $key = $manager->makeKey($tenantId, $context);

        expect($key)->toStartWith("tenant_{$tenantId}_v");
        expect($key)->toEndWith("_{$context}");
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // UTILITY METHOD TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('reports version existence correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = makeTenantCacheManager();

        // Before first access
        expect($manager->hasVersion($tenantId))->toBeFalse();

        // After first access
        $manager->getTenantVersion($tenantId);
        expect($manager->hasVersion($tenantId))->toBeTrue();
    });

    it('forgets version successfully', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = makeTenantCacheManager();

        // Create version
        $manager->getTenantVersion($tenantId);
        expect($manager->hasVersion($tenantId))->toBeTrue();

        // Forget
        $result = $manager->forgetVersion($tenantId);
        expect($result)->toBeTrue();
        expect($manager->hasVersion($tenantId))->toBeFalse();
    });
});
