<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * TenantCacheManagerInterface - Port for Generational Cache Orchestration
 *
 * APPLICATION CONTRACT: Controllers type-hint this interface, not the
 * concrete App\Services\TenantCacheManager.
 */
interface TenantCacheManagerInterface
{
    /**
     * Get the current cache version for a tenant.
     */
    public function getTenantVersion(int|string $tenantId): int;

    /**
     * Rotate the cache version for a tenant (nuclear cache invalidation).
     *
     * @return int The new version timestamp
     */
    public function rotateTenantVersion(int|string $tenantId): int;

    /**
     * Generate a versioned composite cache key for a tenant resource.
     *
     * Example: tenant_1_v1735150000_coupons_public
     */
    public function makeKey(int|string $tenantId, string $context): string;

    /**
     * Check if a tenant version key exists in cache.
     */
    public function hasVersion(int|string $tenantId): bool;

    /**
     * Delete a tenant's version key (forces regeneration on next read).
     */
    public function forgetVersion(int|string $tenantId): bool;
}
