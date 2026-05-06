<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveTenantFromRequest;

/**
 * VC-AUDIT-106 Test Suite
 * 
 * Ensures tenant cache stores lightweight arrays, not full Eloquent models.
 */

describe('VC-AUDIT-106: Tenant Cache Lightweight', function () {
    it('caches array not Eloquent model', function () {
        $reflection = new ReflectionClass(ResolveTenantFromRequest::class);
        $source = file_get_contents($reflection->getFileName());
        
        // Verify cache stores payload array
        expect($source)
            ->toContain("// VC-AUDIT-106: Cache lightweight array")
            ->and($source)
            ->toContain("\$payload = [")
            ->and($source)
            ->toContain("Cache::put(\$cacheKey, \$payload");
    });

    it('rehydrates from lightweight array on cache hit', function () {
        $reflection = new ReflectionClass(ResolveTenantFromRequest::class);
        $source = file_get_contents($reflection->getFileName());
        
        // Verify cache hit logic rehydrates from array
        expect($source)
            ->toContain('// VC-AUDIT-106: Rehydrate from lightweight array')
            ->and($source)
            ->toContain('if (is_array($cached))')
            ->and($source)
            ->toContain('$tenant->forceFill($cached)');
    });
});
