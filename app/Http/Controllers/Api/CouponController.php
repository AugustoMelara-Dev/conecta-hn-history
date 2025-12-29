<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Services\TenantCacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * CouponController - Discount Validation Engine
 *
 * ARCHITECTURE: Generational Caching with Version Rotation
 *
 * Uses versioned cache keys for driver-agnostic invalidation.
 * When a coupon changes, CouponObserver rotates the tenant version,
 * making all old cache entries unreachable.
 *
 * CACHE KEY STRUCTURE:
 *   tenant_{tenantId}_v{version}_coupons_public
 *
 * SECURITY PATTERN:
 * This controller is the "source of truth" for discount calculations.
 * Frontend displays are for UX only; final validation happens here.
 *
 * RACE CONDITION PREVENTION:
 * Usage count is incremented atomically in OrderController::store
 * within the database transaction.
 */
class CouponController extends Controller
{
    /**
     * Cache TTL for coupon listings (15 minutes).
     *
     * VOLATILITY ADJUSTMENT:
     * Marketing campaigns change more frequently than product catalogs.
     */
    protected const CACHE_TTL_MINUTES = 15;

    /**
     * The tenant cache manager instance.
     */
    protected TenantCacheManager $cacheManager;

    /**
     * Create a new controller instance.
     */
    public function __construct(TenantCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Verify a coupon code against cart subtotal.
     *
     * POST /api/coupons/verify
     *
     * NOTE: Verification is NOT cached as it requires real-time usage checks.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'code' => 'required|string|max:50',
            'cart_subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Datos de verificación inválidos',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');
        $code = strtoupper(trim($request->input('code')));
        $cartSubtotal = (float) $request->input('cart_subtotal');

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: Find coupon by tenant and code
        // ═══════════════════════════════════════════════════════════════════════
        $coupon = Coupon::forTenant($tenantId)
            ->byCode($code)
            ->first();

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'error' => 'Cupón no encontrado',
                'error_code' => 'NOT_FOUND',
            ], 422);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: Run validation cascade
        // ═══════════════════════════════════════════════════════════════════════
        $result = $coupon->validate($cartSubtotal);

        if (! $result->isValid) {
            return response()->json($result->toArray(), 422);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: Return success with calculated discount
        // ═══════════════════════════════════════════════════════════════════════
        return response()->json($result->toArray(), 200);
    }

    /**
     * Get available coupons for a tenant (public display).
     *
     * GET /api/coupons?tenant_id=X
     *
     * GENERATIONAL CACHING:
     * Key format: tenant_{id}_v{version}_coupons_public
     * Version rotation invalidates all coupon caches atomically.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id es requerido',
            ], 400);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GENERATIONAL CACHING: Versioned key for atomic invalidation
        // ═══════════════════════════════════════════════════════════════════════
        $cacheKey = $this->cacheManager->makeKey((int) $tenantId, 'coupons_public');
        $ttl = now()->addMinutes(self::CACHE_TTL_MINUTES);

        $coupons = Cache::remember($cacheKey, $ttl, function () use ($tenantId) {
            return Coupon::forTenant($tenantId)
                ->valid()
                ->select(['code', 'name', 'description', 'type', 'value', 'min_spend', 'expires_at'])
                ->get()
                ->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }
}
