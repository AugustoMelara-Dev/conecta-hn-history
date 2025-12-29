<?php

namespace App\Observers;

use App\Models\Coupon;
use App\Services\TenantCacheManager;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * CouponObserver - Promotion Watchdog with Transactional Event Consistency
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSACTIONAL EVENT CONSISTENCY + GENERATIONAL CACHING                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * IMPLEMENTS: ShouldHandleEventsAfterCommit
 * This interface instructs Laravel to DEFER observer execution until after
 * the database transaction COMMITS. This prevents the "Ghost Read" race
 * condition where a concurrent process caches uncommitted data.
 *
 * ARCHITECTURE: Generational Caching via version rotation.
 * O(1) complexity for atomic invalidation across all cache drivers.
 *
 * BUSINESS CRITICAL:
 * When a tenant modifies a coupon, the cache must be invalidated AFTER
 * the transaction commits to ensure data consistency.
 */
class CouponObserver implements ShouldHandleEventsAfterCommit
{
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
     * Handle the Coupon "saved" event.
     *
     * COVERS: Both creation (INSERT) and updates (UPDATE)
     */
    public function saved(Coupon $coupon): void
    {
        $this->rotateTenantVersion($coupon);
    }

    /**
     * Handle the Coupon "deleted" event.
     */
    public function deleted(Coupon $coupon): void
    {
        $this->rotateTenantVersion($coupon);
    }

    /**
     * Handle the Coupon "restored" event.
     */
    public function restored(Coupon $coupon): void
    {
        $this->rotateTenantVersion($coupon);
    }

    /**
     * Rotate the cache version for this coupon's tenant.
     *
     * GENERATIONAL CACHING STRATEGY:
     * We rotate a single version integer shared across products AND coupons.
     * All cache keys for this tenant embed this version:
     *
     *   tenant_1_v1735150000_coupons_public
     *   tenant_1_v1735150000_products_page_1
     *
     * When version rotates → 1735150060, ALL old keys become unreachable.
     * This provides atomic invalidation of the entire tenant's cache.
     *
     * COMPLEXITY: O(1) - One integer change invalidates ALL cached data.
     */
    private function rotateTenantVersion(Coupon $coupon): void
    {
        if (! $coupon->tenant_id) {
            return;
        }

        try {
            $newVersion = $this->cacheManager->rotateTenantVersion($coupon->tenant_id);

            Log::debug("Tenant cache version rotated to {$newVersion}", [
                'tenant_id' => $coupon->tenant_id,
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'trigger' => 'coupon_change',
            ]);

        } catch (\Exception $e) {
            // Cache invalidation is non-critical - log but don't fail
            Log::warning('Tenant version rotation failed: '.$e->getMessage(), [
                'tenant_id' => $coupon->tenant_id,
                'coupon_id' => $coupon->id,
            ]);
        }
    }
}
