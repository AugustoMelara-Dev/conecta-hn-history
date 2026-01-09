<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Exceptions\InvalidCouponException;
use App\Exceptions\TenantNotFoundException;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use App\Services\CouponValidatorService;
use App\Services\InventoryManager;
use App\Services\PriceCalculatorService;
use Illuminate\Support\Facades\Log;

/**
 * CreateOrderHandler - Pure Business Logic for Order Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: DIP + Hexagonal + Transactional Outbox       ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO DB::transaction (handled by Bus Middleware)                        ║
 * ║  - NO App\Models imports (uses Repository interfaces only)              ║
 * ║  - Events dispatched via TransactionalEventDispatcher (Outbox pattern)  ║
 * ║  - STRICT RETURN: CreateOrderResult DTO (no object/array leak)          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class CreateOrderHandler
{
    use LogSanitizer;

    public function __construct(
        private readonly InventoryManager $inventoryManager,
        private readonly PriceCalculatorService $priceCalculator,
        private readonly CouponValidatorService $couponValidator,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly \App\Domain\Identity\Repositories\UserRepositoryInterface $userRepository,
        private readonly TransactionalEventDispatcher $eventDispatcher,
    ) {}

    /**
     * Handle the command.
     *
     * @param CreateOrderCommand $command
     * @return CreateOrderResult
     *
     * @throws InvalidCouponException
     * @throws TenantNotFoundException
     * @throws \App\Exceptions\UserNotFoundException
     */
    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        // STEP 0: RESOLVE CUSTOMER (Gold Standard: Link to User)
        $user = $this->userRepository->findById($command->customerId);
        if (!$user) {
             // Should not happen due to Validator, but Domain Safety First
             throw new \App\Exceptions\UserNotFoundException("User {$command->customerId} not found");
        }

        // STEP 1: INVENTORY VALIDATION & LOCKING
        $lockedProducts = $this->inventoryManager->validateAndLock(
            $command->items,
            $command->tenantId
        );

        // STEP 2: PRICING CALCULATION
        $calculatedItems = $this->priceCalculator->calculate(
            $command->items,
            $lockedProducts
        );

        // STEP 3: CALCULATE SUBTOTAL
        $subtotal = 0;
        foreach ($calculatedItems as $calc) {
            $subtotal += $calc['line_total'];
        }

        // STEP 4: COUPON VALIDATION (if provided)
        $discountAmount = 0;
        $couponCode = null;
        $coupon = null;

        if (!empty($command->couponCode)) {
            $tenant = $this->tenantRepository->findById($command->tenantId);
            
            if ($tenant === null) {
                throw new TenantNotFoundException("Tenant {$command->tenantId} not found");
            }

            $coupon = $this->couponValidator->validate(
                $command->couponCode,
                $user, // Use Resolved User Entity
                $subtotal,
                $tenant
            );

            $discountAmount = $coupon->calculateDiscountUnsafe($subtotal);
            $this->couponValidator->redeem($coupon, $user, null);
            $couponCode = $coupon->code;
        }

        $total = max(0, $subtotal - $discountAmount);

        // STEP 5: GENERATE ORDER NUMBER via Repository
        $orderNumber = $this->orderRepository->generateOrderNumber($command->tenantId);

        // STEP 6: CREATE ORDER via Repository
        $order = $this->orderRepository->createWithItems(
            [
                'tenant_id' => $command->tenantId,
                'customer_id' => $user->id, // Linked Customer
                'order_number' => $orderNumber,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $command->customerPhone ?? '', // Fallback or empty if not in User entity (UserEntity usuallly lacks phone?)
                'customer_address' => $command->customerAddress, // From Command context
                'customer_notes' => $command->customerNotes,
                'delivery_type' => $command->deliveryType,
                'payment_method' => $command->paymentMethod,
                'currency' => $command->currency, // Added field
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'delivery_fee' => 0,
                'discount_amount' => $discountAmount,
                'coupon_code' => $couponCode,
                'total' => $total,
                'status' => 'pending',
            ],
            $calculatedItems
        );

        // STEP 7: LINK COUPON TO ORDER
        if ($coupon) {
            $this->couponValidator->linkToOrder($coupon, $command->requestor, $order->id);
        }

        // STEP 8: DISPATCH DOMAIN EVENT via Transactional Outbox
        $this->eventDispatcher->dispatch(new \App\Domain\Sales\Events\OrderCreated($order));

        // Safe logging (PII redacted)
        Log::info('Order created via Command Bus', $this->sanitizePayload([
            'order_id' => $order->id,
            'tenant_id' => $command->tenantId,
            'total' => $total,
        ]));

        // STEP 9: RETURN STRICT DTO (NO object leak)
        return CreateOrderResult::fromEntity($order);
    }
}

