<?php

declare(strict_types=1);

use App\Services\TenantCacheManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class);

describe('TenantCacheManager', function () {
    beforeEach(function () {
        // Clear cache before each test
        Cache::flush();
        $this->manager = new TenantCacheManager();
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // MEMOIZATION PROPERTY TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('returns consistent version on consecutive calls (memoization)', function () {
        $tenantId = 'test-tenant-'.uniqid();
        
        // First call
        $version1 = $this->manager->getTenantVersion($tenantId);
        
        // Second call - should return same value (memoized)
        $version2 = $this->manager->getTenantVersion($tenantId);
        
        expect($version1)->toBe($version2);
        expect($version1)->toBeInt();
    });

    it('returns integer type', function () {
        $tenantId = 'test-tenant-'.uniqid();
        
        $version = $this->manager->getTenantVersion($tenantId);
        
        expect($version)->toBeInt();
    });

    it('accepts string tenant IDs (UUID support)', function () {
        $uuidTenantId = '550e8400-e29b-41d4-a716-446655440000';
        
        $version = $this->manager->getTenantVersion($uuidTenantId);
        
        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    it('accepts integer tenant IDs', function () {
        $intTenantId = 12345;
        
        $version = $this->manager->getTenantVersion($intTenantId);
        
        expect($version)->toBeInt();
        expect($version)->toBeGreaterThan(0);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // VERSION ROTATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('rotates version on rotateTenantVersion call', function () {
        $tenantId = 'test-tenant-'.uniqid();
        
        $originalVersion = $this->manager->getTenantVersion($tenantId);
        
        // Wait 1 second to ensure new timestamp
        sleep(1);
        
        $newVersion = $this->manager->rotateTenantVersion($tenantId);
        
        expect($newVersion)->toBeGreaterThan($originalVersion);
        expect($newVersion)->toBeInt();
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // KEY GENERATION TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('generates versioned cache key correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        $context = 'products_page_1';
        
        $key = $this->manager->makeKey($tenantId, $context);
        
        expect($key)->toStartWith("tenant_{$tenantId}_v");
        expect($key)->toEndWith("_{$context}");
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // UTILITY METHOD TESTS
    // ═══════════════════════════════════════════════════════════════════════════

    it('reports version existence correctly', function () {
        $tenantId = 'test-tenant-'.uniqid();
        
        // Before first access
        expect($this->manager->hasVersion($tenantId))->toBeFalse();
        
        // After first access
        $this->manager->getTenantVersion($tenantId);
        expect($this->manager->hasVersion($tenantId))->toBeTrue();
    });

    it('forgets version successfully', function () {
        $tenantId = 'test-tenant-'.uniqid();
        
        // Create version
        $this->manager->getTenantVersion($tenantId);
        expect($this->manager->hasVersion($tenantId))->toBeTrue();
        
        // Forget
        $result = $this->manager->forgetVersion($tenantId);
        expect($result)->toBeTrue();
        expect($this->manager->hasVersion($tenantId))->toBeFalse();
    });
});
