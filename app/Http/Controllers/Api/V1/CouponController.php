<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Marketing\Contracts\CouponReader;
use App\Http\Controllers\Controller;
use App\Services\TenantCacheManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * CouponController - Discount Validation Engine
 *
 * ARCHITECTURE:
 * - Hexagonal: Uses CouponReader for data
 * - Strict: final class, strict types
 * - Zero Eloquent: Logic pushed to Reader/Domain
 */
final class CouponController extends Controller
{
    protected const CACHE_TTL_MINUTES = 15;

    public function __construct(
        private readonly TenantCacheManager $cacheManager,
        private readonly CouponReader $reader
    ) {}

    /**
     * Verify a coupon code against cart subtotal.
     * POST /api/coupons/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|integer',
            'code' => 'required|string|max:50',
            'cart_subtotal' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'error' => 'Datos de verificación inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = (int) $request->input('tenant_id');
        $code = strtoupper(trim($request->input('code')));
        $cartSubtotal = (float) $request->input('cart_subtotal');

        // Delegated to Reader: No direct Model::where
        $coupon = $this->reader->findByCode($tenantId, $code);

        if (! $coupon) {
            return response()->json([
                'valid' => false,
                'error' => 'Cupón no encontrado',
                'error_code' => 'NOT_FOUND',
            ], 422);
        }

        // Domain Logic: Validate method on Model is still okay if it's pure domain logic
        // But ideally this should be a Service or Domain Method.
        // Assuming $coupon is an Eloquent Model / Entity that has validate() method.
        // Since Reader returns 'object' (likely Model), this is acceptable for now in 'Active Record' refactoring
        // as long as the QUERY is out of the controller.

        $result = $coupon->validate($cartSubtotal);

        if (! $result->isValid) {
            return response()->json($result->toArray(), 422);
        }

        return response()->json($result->toArray(), 200);
    }

    /**
     * Get available coupons for a tenant.
     * GET /api/coupons?tenant_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) $request->input('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'error' => 'tenant_id required'], 400);
        }

        $cacheKey = $this->cacheManager->makeKey($tenantId, 'coupons_public');

        // Caching is fine in controller or Decorator. Keeping here for now to match logic.
        $coupons = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($tenantId) {
            // Delegated to Reader
            return $this->reader->getValidCoupons($tenantId)->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }
}
