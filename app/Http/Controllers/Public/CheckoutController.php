<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCheckoutRequest;
use App\Models\Coupon;
use App\Services\CouponValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CheckoutController - Secure Checkout API Endpoints
 * 
 * REFACTORED: Moved to Public Namespace. Validation extracted.
 */
class CheckoutController extends Controller
{
    /**
     * Validate a coupon code for the shopping cart.
     * Uses StoreCheckoutRequest for validation.
     */
    public function validateCoupon(StoreCheckoutRequest $request, CouponValidatorService $couponValidator): JsonResponse
    {
        $validated = $request->validated();
        $code = (string) $validated['code'];
        $cartTotal = (float) $validated['cart_total'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        // Tenant Resolution via App Container (Middleware managed)
        $tenant = app()->has('currentTenant') ? app('currentTenant') : null;

        try {
            $coupon = $couponValidator->validate(
                code: $code,
                user: $request->user(), // Nullable for guests
                cartTotal: $cartTotal,
                tenant: $tenant
            );

            $discountAmount = $coupon->calculateDiscountUnsafe($cartTotal, $shippingCost);
            $newTotal = max(0, $cartTotal - $discountAmount);

            return response()->json([
                'valid' => true,
                'code' => $coupon->code,
                'discount_type' => $coupon->type,
                'discount_amount' => round($discountAmount, 2),
                'new_total' => round($newTotal, 2),
                'message' => $this->getSuccessMessage($coupon),
            ], 200);

        } catch (InvalidCouponException $e) {
            Log::info('CheckoutController: Coupon validation failed', [
                'code' => $code,
                'error_code' => $e->getCode(),
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'valid' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                // Defensive data exposure
                'data' => array_intersect_key($e->context(), array_flip([
                    'min_spend', 'required_quantity', 'current_total'
                ])),
            ], 422);
        }
    }

    private function getSuccessMessage(Coupon $coupon): string
    {
        return match ($coupon->type) {
            Coupon::TYPE_PERCENT => "¡Cupón aplicado! {$coupon->value}% de descuento",
            Coupon::TYPE_FIXED => '¡Cupón aplicado! L. '.number_format((float) $coupon->value, 2).' de descuento',
            Coupon::TYPE_FREE_SHIPPING => '¡Cupón aplicado! Envío gratis',
            default => 'Cupón aplicado correctamente',
        };
    }
}
