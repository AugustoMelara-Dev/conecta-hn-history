<?php

declare(strict_types=1);

use App\Policies\TenantAwarePolicy;

/**
 * VC-AUDIT-005: Policy Performance Test
 *
 * Verifies that getUserTenantIds uses memoization to prevent N+1 queries
 */
test('TenantAwarePolicy getUserTenantIds uses memoization', function () {
    $reflection = new ReflectionMethod(TenantAwarePolicy::class, 'getUserTenantIds');
    $source = file_get_contents($reflection->getFileName());

    $lines = explode("\n", $source);
    $methodBody = implode("\n", array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

    // Verify memoization pattern exists
    expect($methodBody)->toContain('isset($user->_memoized_tenant_ids)');
    expect($methodBody)->toContain('_memoized_tenant_ids = $tenantIds;');
});
