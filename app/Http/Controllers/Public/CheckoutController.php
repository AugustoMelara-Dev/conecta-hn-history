<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Sales\Commands\HoldCartInventoryCommand;
use App\Application\Sales\Commands\SyncCheckoutSessionCommand;
use App\Application\Sales\Handlers\HoldCartInventoryHandler;
use App\Application\Sales\Handlers\SyncCheckoutSessionHandler;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\HoldInventoryRequest;
use App\Http\Requests\Public\SyncCartRequest;
use App\Http\Requests\Public\ValidateCouponRequest;
use App\Models\Tenant;
use App\Services\CouponValidatorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

final class CheckoutController extends Controller
{
    public function __construct(
        private LoggerInterface $log,
        private HoldCartInventoryHandler $holdInventoryHandler,
        private SyncCheckoutSessionHandler $syncSessionHandler
    ) {}

    public function getCartSummary(SyncCartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->resolveTenantId($request);

        if ($tenantId === null || $tenantId === '') {
            return response()->json(['message' => 'Tenant identification required.'], 400);
        }

        $command = new SyncCheckoutSessionCommand(
            tenantId: $tenantId,
            sessionId: $validated['session_id'],
            items: $validated['items'],
            subtotal: (float) $validated['subtotal'],
            userId: $request->user()?->id
        );

        $this->syncSessionHandler->handle($command);

        return response()->json([
            'success' => true,
            'message' => 'Cart synchronized successfully',
            'subtotal' => $validated['subtotal'],
        ]);
    }

    public function holdInventory(HoldInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->resolveTenantId($request);

        if ($tenantId === null || $tenantId === '') {
            return response()->json(['message' => 'Tenant identification required.'], 400);
        }

        try {
            $locationId = $validated['location_id'] ?? DB::table('locations')
                ->where('tenant_id', $tenantId)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->value('id');

            if (! is_string($locationId) || $locationId === '') {
                throw new DomainException('No active primary location found for this tenant.');
            }

            $command = new HoldCartInventoryCommand(
                tenantId: $tenantId,
                locationId: $locationId,
                sessionId: $validated['session_id'],
                items: $validated['items']
            );

            $this->holdInventoryHandler->handle($command);

            return response()->json([
                'success' => true,
                'message' => 'Inventory successfully held for 15 minutes.',
            ]);
        } catch (DomainException $e) {
            $this->log->warning('Inventory hold failed', [
                'session_id' => $validated['session_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function validateCoupon(ValidateCouponRequest $request, CouponValidatorService $couponValidator): JsonResponse
    {
        $validated = $request->validated();
        $tenant = $this->resolveTenant($request);

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'valid' => false,
                'code' => 'TENANT_REQUIRED',
                'message' => 'Tenant identification required.',
            ], 400);
        }

        $code = (string) $validated['code'];
        $cartTotal = (float) $validated['cart_total'];
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        try {
            $coupon = $couponValidator->validate(
                code: $code,
                user: $request->user(),
                cartTotal: $cartTotal,
                tenant: $tenant
            );

            $discountAmount = $coupon->calculateDiscountUnsafe($cartTotal, $shippingCost);
            $newTotal = max(0, $cartTotal - $discountAmount);

            return response()->json([
                'valid' => true,
                'code' => (string) $coupon->code,
                'discount_type' => (string) $coupon->type,
                'discount_amount' => round($discountAmount, 2),
                'new_total' => round($newTotal, 2),
                'message' => $this->getSuccessMessage((string) $coupon->type, (float) $coupon->value),
            ], 200);
        } catch (InvalidCouponException $e) {
            $this->log->info('CheckoutController: Coupon validation failed', [
                'code' => $code,
                'error_code' => $e->getErrorCode(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'valid' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'data' => array_intersect_key($e->context(), array_flip([
                    'min_spend',
                    'required_quantity',
                    'current_total',
                ])),
            ], 422);
        }
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $subtotal = (float) $request->input('subtotal', 0);
        $shippingCost = (float) $request->input('shipping_cost', 0);

        return response()->json([
            'discount_amount' => 0.0,
            'applied_coupon' => null,
            'total' => round($subtotal + $shippingCost, 2),
        ]);
    }

    private function getSuccessMessage(string $type, float $value): string
    {
        return match ($type) {
            'percent' => "¡Cupón aplicado! {$value}% de descuento",
            'fixed' => '¡Cupón aplicado! L. '.number_format($value, 2).' de descuento',
            'free_shipping' => '¡Cupón aplicado! Envío gratis',
            default => 'Cupón aplicado correctamente',
        };
    }

    private function resolveTenant(Request $request): Tenant|string|int|null
    {
        $tenant = $request->route('tenant') ?? $request->get('tenant');

        if ($tenant instanceof Tenant || is_string($tenant) || is_int($tenant)) {
            return $tenant;
        }

        $tenant = $request->attributes->get('tenant');

        if ($tenant instanceof Tenant || is_string($tenant) || is_int($tenant)) {
            return $tenant;
        }

        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');

            if ($tenant instanceof Tenant || is_string($tenant) || is_int($tenant)) {
                return $tenant;
            }
        }

        return null;
    }

    private function resolveTenantId(Request $request): ?string
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant instanceof Tenant) {
            return (string) $tenant->getKey();
        }

        if (is_string($tenant) || is_int($tenant)) {
            return (string) $tenant;
        }

        return null;
    }
}
