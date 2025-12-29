<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InvalidCouponException;
use App\Models\Coupon;
use App\Services\CouponValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutController - Secure Checkout API Endpoints
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY-FIRST: All responses sanitized to prevent data leakage       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * FEATURES:
 * - Guest-compatible coupon validation (nullable user)
 * - Structured JSON responses for React frontend
 * - Error handling with user-friendly messages
 * - Data hiding (no raw Eloquent models in responses)
 */
class CheckoutController extends Controller
{
    /**
     * Validate a coupon code for the shopping cart.
     *
     * SECURITY CONSIDERATIONS:
     * ════════════════════════════════════════════════════════════════════════
     * 1. DATA HIDING: Never return raw Coupon model (exposes internal fields)
     * 2. GUEST SUPPORT: $request->user() returns null for unauthenticated users
     * 3. TENANT CONTEXT: Resolved from app container (set by TenantMiddleware)
     * 4. ERROR HANDLING: Returns structured 422 for validation failures
     *
     *
     * @example Success Response (HTTP 200):
     * {
     *   "valid": true,
     *   "code": "SAVE10",
     *   "discount_type": "percent",
     *   "discount_amount": 15.00,
     *   "new_total": 135.00,
     *   "message": "Cupón aplicado correctamente"
     * }
     * @example Error Response (HTTP 422):
     * {
     *   "valid": false,
     *   "code": "EXPIRED",
     *   "message": "Este cupón ha expirado"
     * }
     */
    public function validateCoupon(Request $request, CouponValidatorService $couponValidator): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // INPUT VALIDATION
        // ═══════════════════════════════════════════════════════════════════════
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'cart_total' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $code = (string) $validated['code'];
        $cartTotal = (float) $validated['cart_total'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        // ═══════════════════════════════════════════════════════════════════════
        // TENANT RESOLUTION
        // ═══════════════════════════════════════════════════════════════════════
        // For guest checkout, tenant comes from app container (set by middleware)
        $tenant = null;
        if (app()->has('currentTenant')) {
            $tenant = app('currentTenant');
        }

        try {
            // ═══════════════════════════════════════════════════════════════════
            // COUPON VALIDATION
            // ═══════════════════════════════════════════════════════════════════
            // $request->user() returns null for guests - CouponValidatorService
            // is designed to handle this gracefully.
            $coupon = $couponValidator->validate(
                code: $code,
                user: $request->user(),
                cartTotal: $cartTotal,
                tenant: $tenant
            );

            // ═══════════════════════════════════════════════════════════════════
            // DISCOUNT CALCULATION
            // ═══════════════════════════════════════════════════════════════════
            $discountAmount = $coupon->calculateDiscountUnsafe($cartTotal, $shippingCost);
            $newTotal = max(0, $cartTotal - $discountAmount);

            // ═══════════════════════════════════════════════════════════════════
            // SANITIZED SUCCESS RESPONSE
            // ═══════════════════════════════════════════════════════════════════
            // CRITICAL: Only return derived data needed by frontend.
            // NEVER return the raw $coupon model (exposes internal fields).
            return response()->json([
                'valid' => true,
                'code' => $coupon->code,
                'discount_type' => $coupon->type,
                'discount_amount' => round($discountAmount, 2),
                'new_total' => round($newTotal, 2),
                'message' => $this->getSuccessMessage($coupon),
            ], 200);

        } catch (InvalidCouponException $e) {
            // ═══════════════════════════════════════════════════════════════════
            // STRUCTURED ERROR RESPONSE
            // ═══════════════════════════════════════════════════════════════════
            Log::info('CheckoutController: Coupon validation failed', [
                'context' => 'coupon_validation_failed',
                'code' => $code,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'is_guest' => $request->user() === null,
            ]);

            return response()->json([
                'valid' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'data' => $e->getData(), // Contains min_spend, remaining, etc.
            ], 422);
        }
    }

    /**
     * Generate a user-friendly success message based on coupon type.
     */
    private function getSuccessMessage(Coupon $coupon): string
    {
        return match ($coupon->type) {
            Coupon::TYPE_PERCENT => "¡Cupón aplicado! {$coupon->value}% de descuento",
            Coupon::TYPE_FIXED => '¡Cupón aplicado! L. '.number_format((float) $coupon->value, 2).' de descuento',
            Coupon::TYPE_FREE_SHIPPING => '¡Cupón aplicado! Envío gratis',
            default => 'Cupón aplicado correctamente',
        };
    }

    /**
     * Get current cart summary with applied discounts.
     *
     * This endpoint is used by the frontend to refresh the cart display
     * after applying or removing a coupon.
     */
    public function getCartSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['sometimes', 'numeric', 'min:0'],
            'coupon_code' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $subtotal = (float) $validated['subtotal'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $couponCode = $validated['coupon_code'] ?? null;

        $discountAmount = 0.0;
        $discountType = null;
        $appliedCouponCode = null;

        // If coupon code provided, try to apply it silently
        if ($couponCode !== null && $couponCode !== '') {
            try {
                $tenant = app()->has('currentTenant') ? app('currentTenant') : null;
                $couponValidator = app(CouponValidatorService::class);

                $coupon = $couponValidator->validate(
                    code: $couponCode,
                    user: $request->user(),
                    cartTotal: $subtotal,
                    tenant: $tenant
                );

                $discountAmount = $coupon->calculateDiscountUnsafe($subtotal, $shippingCost);
                $discountType = $coupon->type;
                $appliedCouponCode = $coupon->code;
            } catch (InvalidCouponException) {
                // Silently ignore invalid coupon
            }
        }

        $total = max(0, $subtotal + $shippingCost - $discountAmount);

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => round($shippingCost, 2),
            'discount_amount' => round($discountAmount, 2),
            'discount_type' => $discountType,
            'applied_coupon' => $appliedCouponCode,
            'total' => round($total, 2),
        ], 200);
    }

    /**
     * Remove applied coupon from cart.
     *
     * Simple endpoint that just returns the cart without any coupon applied.
     */
    public function removeCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $subtotal = (float) $validated['subtotal'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => round($shippingCost, 2),
            'discount_amount' => 0.0,
            'discount_type' => null,
            'applied_coupon' => null,
            'total' => round($subtotal + $shippingCost, 2),
            'message' => 'Cupón removido',
        ], 200);
    }
}
