<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * TenantCacheManager - Generational Caching Orchestrator
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DRIVER-AGNOSTIC CACHE INVALIDATION VIA VERSION ROTATION                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PROBLEM SOLVED:
 * Cache::tags() only works with Redis/Memcached. File/Database drivers fail
 * silently or throw exceptions. This creates deployment risk and stale data.
 *
 * SOLUTION: Generational Caching (Version Stamping)
 * Instead of tagging, we embed a version number in every cache key:
 *   tenant_1_v1735150000_products_page_1
 *
 * When a product changes, we rotate the version (1735150000 → 1735150060).
 * All old keys become unreachable and expire naturally via TTL.
 *
 * COMPLEXITY: O(1) for invalidating millions of cache entries.
 *
 * COMPATIBILITY: Works with ALL cache drivers (file, database, redis, memcached).
 */
class TenantCacheManager
{
    /**
     * Prefix for version keys to prevent collisions.
     */
    protected const VERSION_KEY_PREFIX = 'tenant_version_';

    /**
     * Get the current cache version for a tenant.
     *
     * SELF-HEALING LOGIC:
     * If the version key doesn't exist (first run or cache cleared),
     * we generate a new timestamp and persist it forever.
     *
     * @param  int  $tenantId  The tenant identifier
     * @return int The current version timestamp
     */
    public function getTenantVersion(int $tenantId): int
    {
        $key = self::VERSION_KEY_PREFIX.$tenantId;

        // ═══════════════════════════════════════════════════════════════════════
        // CACHE-ASIDE PATTERN: Read version or generate new one
        // ═══════════════════════════════════════════════════════════════════════
        $version = Cache::get($key);

        if ($version === null) {
            // First run or cache cleared - generate new version
            $version = now()->timestamp;
            Cache::forever($key, $version);
        }

        return (int) $version;
    }

    /**
     * Rotate the cache version for a tenant (NUCLEAR INVALIDATION).
     *
     * EFFECT:
     * By overwriting the version with a new timestamp, ALL downstream
     * composite keys become unreachable. Old data expires naturally.
     *
     * PERFORMANCE: O(1) - Changes one integer to invalidate millions of keys.
     *
     * USE CASE:
     * Called from Model Observers when products/coupons are saved/deleted.
     *
     * @param  int  $tenantId  The tenant identifier
     * @return int The new version timestamp
     */
    public function rotateTenantVersion(int $tenantId): int
    {
        $key = self::VERSION_KEY_PREFIX.$tenantId;
        $newVersion = now()->timestamp;

        // Atomic overwrite - old version is immediately obsolete
        Cache::forever($key, $newVersion);

        return $newVersion;
    }

    /**
     * Generate a versioned cache key for a tenant resource.
     *
     * COMPOSITE KEY STRUCTURE:
     *   tenant_{tenantId}_v{version}_{context}
     *
     * EXAMPLES:
     *   - tenant_1_v1735150000_products_page_1_per_20
     *   - tenant_1_v1735150000_coupons_public
     *   - tenant_1_v1735150000_product_42
     *
     * @param  int  $tenantId  The tenant identifier
     * @param  string  $context  Resource-specific context (e.g., "products_page_1")
     * @return string The composite cache key
     */
    public function makeKey(int $tenantId, string $context): string
    {
        $version = $this->getTenantVersion($tenantId);

        return "tenant_{$tenantId}_v{$version}_{$context}";
    }

    /**
     * Check if a tenant version exists.
     *
     * Useful for debugging and health checks.
     *
     * @param  int  $tenantId  The tenant identifier
     * @return bool Whether the version key exists
     */
    public function hasVersion(int $tenantId): bool
    {
        return Cache::has(self::VERSION_KEY_PREFIX.$tenantId);
    }

    /**
     * Delete a tenant's version key (for testing/debugging).
     *
     * WARNING: This will cause a version regeneration on next read,
     * effectively invalidating all cached data for this tenant.
     *
     * @param  int  $tenantId  The tenant identifier
     * @return bool Whether the key was deleted
     */
    public function forgetVersion(int $tenantId): bool
    {
        return Cache::forget(self::VERSION_KEY_PREFIX.$tenantId);
    }
}
