<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CouponController - Discount Validation Engine
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
     * Verify a coupon code against cart subtotal.
     * 
     * POST /api/coupons/verify
     * 
     * Request: { tenant_id, code, cart_subtotal }
     * 
     * Success Response 200:
     * {
     *   "valid": true,
     *   "discount_amount": 50.00,
     *   "new_total": 450.00,
     *   "coupon_code": "VERANO",
     *   "coupon_type": "fixed",
     *   "coupon_value": 50
     * }
     * 
     * Error Response 422:
     * {
     *   "valid": false,
     *   "error": "Este cupón ha expirado",
     *   "error_code": "EXPIRED"
     * }
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

        if (!$coupon) {
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

        if (!$result['valid']) {
            return response()->json($result, 422);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: Return success with calculated discount
        // ═══════════════════════════════════════════════════════════════════════
        return response()->json($result, 200);
    }

    /**
     * Get available coupons for a tenant (public display).
     * Only shows active, non-expired coupons without usage limits reached.
     * 
     * GET /api/coupons?tenant_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_id es requerido',
            ], 400);
        }

        $coupons = Coupon::forTenant($tenantId)
            ->valid()
            ->select(['code', 'name', 'description', 'type', 'value', 'min_spend', 'expires_at'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }
}
