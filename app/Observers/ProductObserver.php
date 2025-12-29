<?php

namespace App\Observers;

use App\Contracts\ResetsStaticCache;
use App\Jobs\GenerateProductBlurhash;
use App\Models\Product;
use App\Services\TenantCacheManager;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProductObserver - Inventory Watchdog with Generational Cache Invalidation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSACTIONAL EVENT CONSISTENCY + GENERATIONAL CACHING                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * IMPLEMENTS: ShouldHandleEventsAfterCommit
 * This interface instructs Laravel to DEFER observer execution until after
 * the database transaction COMMITS. This prevents the "Ghost Read" race
 * condition where a concurrent process reads stale data during rotation.
 *
 * ARCHITECTURE: Generational Caching
 * Version rotation makes ALL existing cache entries unreachable. O(1) complexity.
 *
 * COMPATIBILITY: Works with ALL cache drivers (file, database, redis, memcached).
 *
 * RESPONSIBILITIES:
 * 1. Cache invalidation via version rotation (after commit)
 * 2. Auto-assign city_id from tenant (data integrity)
 * 3. Blurhash generation dispatch (async - CPU intensive)
 */
class ProductObserver implements ResetsStaticCache, ShouldHandleEventsAfterCommit
{
    /**
     * MEMOIZATION CACHE: Request-Level Tenant City Storage
     *
     * @var array<int, int|null> Maps tenant_id => city_id
     */
    protected static array $tenantCityCache = [];

    /**
     * The tenant cache manager instance.
     */
    protected TenantCacheManager $cacheManager;

    /**
     * Create a new observer instance.
     */
    public function __construct(TenantCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * INFRASTRUCTURE METHOD: Deterministic Garbage Collection
     *
     * Called by Queue::after() in AppServiceProvider to prevent state pollution
     * in long-running queue workers.
     */
    public static function flushStaticCache(): void
    {
        static::$tenantCityCache = [];
    }

    /**
     * Handle the Product "creating" event.
     *
     * RESPONSIBILITIES:
     * 1. Auto-generate URL-friendly slug from product name (if not provided)
     * 2. Auto-assign city_id from tenant to prevent invisible products
     */
    public function creating(Product $product): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // AUTO-SLUG GENERATION: SEO-Friendly URLs
        // ═══════════════════════════════════════════════════════════════════════
        // Generate unique slug scoped to tenant if not provided.
        // Pattern: "pizza-pepperoni" or "pizza-pepperoni-2" on collision.
        if (empty($product->slug) && ! empty($product->name)) {
            $product->slug = $this->generateUniqueSlug($product);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // CITY SYNCHRONIZATION: Inherit from Tenant
        // ═══════════════════════════════════════════════════════════════════════
        if ($product->city_id === null && $product->tenant_id !== null) {
            $cityId = $this->resolveTenantCityId($product->tenant_id);

            if ($cityId !== null) {
                $product->city_id = $cityId;
            }
        }
    }

    /**
     * Generate a unique slug scoped to the tenant.
     *
     * UNIQUENESS STRATEGY:
     * Slugs must be unique within a tenant (not globally).
     * This allows different tenants to have "pizza-pepperoni" products.
     *
     * COLLISION HANDLING:
     * If "pizza-pepperoni" exists, tries "pizza-pepperoni-2", "pizza-pepperoni-3", etc.
     */
    protected function generateUniqueSlug(Product $product): string
    {
        $baseSlug = Str::slug($product->name);
        $slug = $baseSlug;
        $counter = 2;

        // Check for collisions within same tenant (including soft-deleted)
        while (
            Product::withTrashed()
                ->where('tenant_id', $product->tenant_id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Resolve tenant's city_id using Cache-Aside Pattern.
     */
    protected function resolveTenantCityId(int $tenantId): ?int
    {
        if (array_key_exists($tenantId, static::$tenantCityCache)) {
            return static::$tenantCityCache[$tenantId];
        }

        $tenant = \App\Models\Tenant::withoutGlobalScopes()
            ->select('city_id')
            ->find($tenantId);

        $cityId = $tenant?->city_id;
        static::$tenantCityCache[$tenantId] = $cityId;

        return $cityId;
    }

    /**
     * Handle the Product "saved" event.
     *
     * RESPONSIBILITIES:
     * 1. Cache invalidation via version rotation (O(1))
     * 2. Blurhash generation dispatch (async)
     */
    public function saved(Product $product): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // GENERATIONAL CACHE INVALIDATION: Rotate tenant version
        // ═══════════════════════════════════════════════════════════════════════
        $this->rotateTenantVersion($product);

        // ═══════════════════════════════════════════════════════════════════════
        // ASYNC BLURHASH: Offload CPU-intensive image processing to queue
        // ═══════════════════════════════════════════════════════════════════════
        if ($product->isDirty('image_path') && ! empty($product->image_path)) {
            GenerateProductBlurhash::dispatch($product->id, $product->image_path);
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $this->rotateTenantVersion($product);
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        $this->rotateTenantVersion($product);
    }

    /**
     * Rotate the cache version for this product's tenant.
     *
     * GENERATIONAL CACHING STRATEGY:
     * Instead of tracking individual cache keys (pagination nightmare),
     * we rotate a single version integer. All cache keys embed this version:
     *
     *   tenant_1_v1735150000_products_page_1
     *
     * When version rotates → 1735150060, ALL old keys become unreachable.
     * Old data expires naturally via TTL. New requests get fresh data.
     *
     * COMPLEXITY: O(1) - One integer change invalidates ALL pages.
     */
    private function rotateTenantVersion(Product $product): void
    {
        if (! $product->tenant_id) {
            return;
        }

        try {
            $newVersion = $this->cacheManager->rotateTenantVersion($product->tenant_id);

            Log::debug("Tenant cache version rotated to {$newVersion}", [
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'trigger' => 'product_change',
            ]);

        } catch (\Exception $e) {
            // Cache invalidation is non-critical - log but don't fail
            Log::warning('Tenant version rotation failed: '.$e->getMessage(), [
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
            ]);
        }
    }
}
