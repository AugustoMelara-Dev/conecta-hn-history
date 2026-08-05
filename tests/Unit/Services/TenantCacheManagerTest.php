<?php

declare(strict_types=1);

use App\Services\TenantCacheManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

describe('TenantCacheManager', function () {
    beforeEach(function () {
        Cache::flush();
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // MEMOIZATION PROPERTY TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('returns consistent version on consecutive calls (memoization)', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        // First call
        $version1 = $manager->getTenantVersion($tenantId);

        // Second call - should return same value (memoized)
        $version2 = $manager->getTenantVersion($tenantId);

        expect($version1)->toBe($version2);
        expect($version1)->toBeInt();
    });

    it('returns integer type', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        $version = $manager->getTenantVersion($tenantId);

        expect($version)->toBeInt();
    });

    it('accepts string tenant IDs (UUID support)', function () {
        $uuidTenantId = '550e8400-e29b-41d4-a716-446655440000';
        $manager = new TenantCacheManager;

        $version = $manager->getTenantVersion($uuidTenantId);

        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    it('accepts another UUID tenant ID', function () {
        $tenantId = '550e8400-e29b-41d4-a716-446655440001';
        $manager = new TenantCacheManager;

        $version = $manager->getTenantVersion($tenantId);

        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // VERSION ROTATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('rotates version on rotateTenantVersion call', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        $originalVersion = $manager->getTenantVersion($tenantId);

        $newVersion = $manager->rotateTenantVersion($tenantId);

        expect($newVersion)->toBeGreaterThan($originalVersion);
        expect($newVersion)->toBeInt();
    });

    it('rotates monotonically more than once in the same second', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        $version1 = $manager->getTenantVersion($tenantId);
        $version2 = $manager->rotateTenantVersion($tenantId);
        $version3 = $manager->rotateTenantVersion($tenantId);

        expect($version2)->toBeGreaterThan($version1);
        expect($version3)->toBeGreaterThan($version2);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // KEY GENERATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('generates versioned cache key correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $context = 'products_page_1';
        $manager = new TenantCacheManager;

        $key = $manager->makeKey($tenantId, $context);

        expect($key)->toStartWith("tenant_{$tenantId}_v");
        expect($key)->toEndWith("_{$context}");
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // UTILITY METHOD TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('reports version existence correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        // Before first access
        expect($manager->hasVersion($tenantId))->toBeFalse();

        // After first access
        $manager->getTenantVersion($tenantId);
        expect($manager->hasVersion($tenantId))->toBeTrue();
    });

    it('forgets version successfully', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $manager = new TenantCacheManager;

        // Create version
        $manager->getTenantVersion($tenantId);
        expect($manager->hasVersion($tenantId))->toBeTrue();

        // Forget
        $result = $manager->forgetVersion($tenantId);
        expect($result)->toBeTrue();
        expect($manager->hasVersion($tenantId))->toBeFalse();
    });
});
