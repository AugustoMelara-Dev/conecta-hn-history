<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCheckoutRequest;
use App\Services\CouponValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutController - Hexagonal Compliant (DIP Remediation)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Zero App\Models imports.
 * - Uses primitives for message generation.
 * - Tenant resolution via request attributes (middleware-set).
 */
final class CheckoutController extends Controller
{
    public function __construct() {}

    public function validateCoupon(StoreCheckoutRequest $request, CouponValidatorService $couponValidator): JsonResponse
    {
        $validated = $request->validated();
        $code = (string) $validated['code'];
        $cartTotal = (float) $validated['cart_total'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        // Tenant resolution: middleware-set attribute or route parameter
        $tenant = $request->route('tenant') ?? $request->get('tenant');

        try {
            // CouponValidatorService returns a validated Coupon model internally,
            // but we only extract primitives from it for the response.
            $coupon = $couponValidator->validate(
                code: $code,
                user: $request->user(),
                cartTotal: $cartTotal,
                tenant: $tenant
            );

            // Extract primitives BEFORE returning - no Model dependency in Controller
            $discountAmount = $coupon->calculateDiscountUnsafe($cartTotal, $shippingCost);
            $newTotal = max(0, $cartTotal - $discountAmount);

            // Extract primitive data for message generation
            $couponType = (string) $coupon->type;
            $couponValue = (float) $coupon->value;
            $couponCode = (string) $coupon->code;

            return response()->json([
                'valid' => true,
                'code' => $couponCode,
                'discount_type' => $couponType,
                'discount_amount' => round($discountAmount, 2),
                'new_total' => round($newTotal, 2),
                'message' => $this->getSuccessMessage($couponType, $couponValue),
            ], 200);

        } catch (InvalidCouponException $e) {
            Log::info('CheckoutController: Coupon validation failed', [
                'code' => $code,
                'error_code' => $e->getCode(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'valid' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'data' => array_intersect_key($e->context(), array_flip([
                    'min_spend', 'required_quantity', 'current_total',
                ])),
            ], 422);
        }
    }

    public function getCartSummary(Request $request, CouponValidatorService $couponValidator): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        $subtotal = (float) $validated['subtotal'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);
        $discountAmount = 0.0;
        $discountType = null;
        $appliedCoupon = null;

        if (! empty($validated['coupon_code'])) {
            $tenant = $request->route('tenant') ?? $request->get('tenant');

            try {
                $coupon = $couponValidator->validate(
                    code: (string) $validated['coupon_code'],
                    user: $request->user(),
                    cartTotal: $subtotal,
                    tenant: $tenant
                );

                $discountAmount = round($coupon->calculateDiscountUnsafe($subtotal, $shippingCost), 2);
                $discountType = (string) $coupon->type;
                $appliedCoupon = (string) $coupon->code;
            } catch (InvalidCouponException) {
                $discountAmount = 0.0;
            }
        }

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'shipping_cost' => round($shippingCost, 2),
            'discount_amount' => $discountAmount,
            'discount_type' => $discountType,
            'applied_coupon' => $appliedCoupon,
            'total' => round(max(0, $subtotal + $shippingCost - $discountAmount), 2),
        ]);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
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
        ]);
    }

    /**
     * Generate success message using primitives only.
     *
     * @param  string  $type  Coupon type constant value (percent, fixed, free_shipping)
     * @param  float  $value  Discount value
     */
    private function getSuccessMessage(string $type, float $value): string
    {
        // Using string literals instead of Model constants to avoid import
        return match ($type) {
            'percent' => "¡Cupón aplicado! {$value}% de descuento",
            'fixed' => '¡Cupón aplicado! L. '.number_format($value, 2).' de descuento',
            'free_shipping' => '¡Cupón aplicado! Envío gratis',
            default => 'Cupón aplicado correctamente',
        };
    }
}
