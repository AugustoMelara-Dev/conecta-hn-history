<?php

namespace App\Observers;

use App\Contracts\ResetsStaticCache;
use App\Jobs\GenerateProductBlurhash;
use App\Models\Product;

class ProductObserver implements ResetsStaticCache
{
    /**
     * MEMOIZATION CACHE: Request-Level Tenant City Storage
     *
     * PURPOSE:
     * Stores tenant_id => city_id mappings in memory to avoid
     * redundant database queries during bulk import operations.
     *
     * PERFORMANCE IMPACT:
     * - Without cache: 10,000 products = 10,000 SELECT queries
     * - With cache: 10,000 products = 1 SELECT query per unique tenant
     *
     * MEMORY STRATEGY:
     * Stores ONLY primitive int|null values (not Eloquent models)
     * to prevent memory leaks in long-running processes.
     *
     * LIFECYCLE:
     * Static property persists for the entire PHP process lifecycle
     * (single HTTP request or queue job execution).
     *
     * @var array<int, int|null> Maps tenant_id => city_id
     */
    protected static array $tenantCityCache = [];

    /**
     * INFRASTRUCTURE METHOD: Deterministic Garbage Collection
     *
     * ╔══════════════════════════════════════════════════════════════════════╗
     * ║  ⚠️  WARNING: THIS IS NOT A BUSINESS LOGIC METHOD                    ║
     * ║                                                                      ║
     * ║  This method exists solely for INFRASTRUCTURE purposes:             ║
     * ║  - Queue Worker memory hygiene (prevents state pollution)           ║
     * ║  - Test suite tearDown/setUp (ensures test isolation)               ║
     * ║                                                                      ║
     * ║  DO NOT CALL THIS METHOD FROM CONTROLLERS, SERVICES, OR JOBS.       ║
     * ║  It is automatically invoked by the Queue::after event listener     ║
     * ║  registered in AppServiceProvider::boot().                          ║
     * ╚══════════════════════════════════════════════════════════════════════╝
     *
     * DAEMON PROCESS CONTEXT:
     * Queue Workers (php artisan queue:work) are long-running daemon processes.
     * Unlike HTTP requests that die after response, workers persist in memory
     * and process thousands of jobs sequentially. Static properties survive
     * across job boundaries, causing "state pollution" where Job B reads
     * stale data cached by Job A hours earlier.
     *
     * This method provides an escape hatch to purge the static cache,
     * ensuring each job starts with a clean memory slate.
     *
     * USAGE (Infrastructure only):
     *   // In AppServiceProvider::boot()
     *   Queue::after(fn() => ProductObserver::flushStaticCache());
     *
     *   // In test tearDown()
     *   ProductObserver::flushStaticCache();
     */
    public static function flushStaticCache(): void
    {
        static::$tenantCityCache = [];
    }

    /**
     * Handle the Product "creating" event.
     *
     * SECURITY DIRECTIVE 3: Data Synchronization
     * Auto-assign city_id from tenant to prevent invisible products.
     *
     * PERFORMANCE OPTIMIZATION: Memoization Pattern
     * Uses static cache to eliminate N+1 queries during bulk imports.
     * Complexity: O(1) per tenant instead of O(N) per product.
     */
    public function creating(Product $product): void
    {
        // Guard: Only proceed if city needs to be inherited
        // TYPE SAFETY: Use !== null instead of empty()
        // to properly handle city_id = 0 as a valid value
        if ($product->city_id !== null || $product->tenant_id === null) {
            return;
        }

        // Resolve city_id using memoized lookup
        $cityId = $this->resolveTenantCityId($product->tenant_id);

        if ($cityId !== null) {
            $product->city_id = $cityId;
        }
    }

    /**
     * Resolve tenant's city_id using Cache-Aside Pattern.
     *
     * ALGORITHM:
     * 1. Check static memory cache (O(1) lookup)
     * 2. If cache miss, query database and store result
     * 3. Return cached/fetched value
     *
     * EDGE CASE HANDLING:
     * - Tenant not found: Stores NULL to prevent retry storms
     * - Tenant has no city: Stores NULL to prevent retry storms
     *
     * @param  int  $tenantId  The tenant to lookup
     * @return int|null The city_id or null if not available
     */
    protected function resolveTenantCityId(int $tenantId): ?int
    {
        // ═══════════════════════════════════════════════════════════════════
        // CACHE-ASIDE PATTERN: Check memory first
        // ═══════════════════════════════════════════════════════════════════
        if (array_key_exists($tenantId, static::$tenantCityCache)) {
            // Cache HIT: Return memoized value (including null)
            return static::$tenantCityCache[$tenantId];
        }

        // ═══════════════════════════════════════════════════════════════════
        // CACHE MISS: Query database and memoize result
        // ═══════════════════════════════════════════════════════════════════
        $tenant = \App\Models\Tenant::withoutGlobalScopes()
            ->select('city_id')  // Minimal memory footprint
            ->find($tenantId);

        // Extract PRIMITIVE value only (not the model object)
        $cityId = $tenant?->city_id;

        // Memoize the result (including null for non-existent tenants)
        // This prevents "retry storms" for missing/incomplete data
        static::$tenantCityCache[$tenantId] = $cityId;

        return $cityId;
    }

    /**
     * Handle the Product "saved" event.
     *
     * RESPONSIBILITIES:
     * 1. Cache invalidation (sync - fast)
     * 2. Blurhash generation (async - CPU intensive)
     */
    public function saved(Product $product): void
    {
        // ═══════════════════════════════════════════════════════════════════
        // CACHE INVALIDATION: Flush tenant's cached catalog (sync - fast)
        // ═══════════════════════════════════════════════════════════════════
        $this->invalidateTenantCache($product);

        // ═══════════════════════════════════════════════════════════════════
        // ASYNC BLURHASH: Offload CPU-intensive image processing to queue
        // This prevents 100-500ms blocking during bulk imports
        //
        // OPTIMISTIC CONCURRENCY: Pass current image_path as snapshot.
        // If image changes before job runs, job will detect and skip.
        // ═══════════════════════════════════════════════════════════════════
        if ($product->isDirty('image_path') && ! empty($product->image_path)) {
            GenerateProductBlurhash::dispatch($product->id, $product->image_path);
        }
    }

    /**
     * Handle the Product "deleted" event.
     *
     * CACHE INVALIDATION:
     * Also flush on delete to remove stale catalog entries.
     */
    public function deleted(Product $product): void
    {
        $this->invalidateTenantCache($product);
    }

    /**
     * Invalidate all cached data for this product's tenant.
     *
     * TAGGED CACHING STRATEGY:
     * All cache entries for a tenant are tagged with 'tenant:{id}'.
     * This allows atomic invalidation of an entire tenant's cache
     * without affecting other tenants (cache isolation).
     *
     * GRACEFUL DEGRADATION:
     * Uses explicit Cache::supportsTags() check to prevent
     * BadMethodCallException on drivers that don't support tagging
     * (file, database, array). This allows local dev environments
     * to function without Redis/Memcached.
     */
    private function invalidateTenantCache(Product $product): void
    {
        // Guard: Skip if no tenant context
        if (! $product->tenant_id) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════
        // GUARD CLAUSE: Explicit driver capability check
        // Prevents BadMethodCallException on file/database/array drivers
        // ═══════════════════════════════════════════════════════════════════
        if (! \Illuminate\Support\Facades\Cache::supportsTags()) {
            // Driver doesn't support tags - gracefully skip invalidation
            // This is expected in dev environments with CACHE_DRIVER=file
            return;
        }

        try {
            \Illuminate\Support\Facades\Cache::tags(['tenant:'.$product->tenant_id])->flush();

            \Illuminate\Support\Facades\Log::debug(
                "Cache invalidated for tenant {$product->tenant_id} due to product change",
                ['product_id' => $product->id]
            );
        } catch (\Exception $e) {
            // Cache invalidation is non-critical - log but don't fail
            \Illuminate\Support\Facades\Log::warning(
                'Cache invalidation failed: '.$e->getMessage(),
                ['tenant_id' => $product->tenant_id, 'product_id' => $product->id]
            );
        }
    }
}
