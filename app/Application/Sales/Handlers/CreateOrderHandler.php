<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Contracts\CouponValidatorInterface;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\InventoryManagerInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\PriceCalculatorInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Sales\Events\OrderCreated;
use App\Domain\Sales\Factories\OrderFactory;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Models\InventoryHold;

/**
 * CreateOrderHandler - Pure Business Logic for Order Creation
 */
final readonly class CreateOrderHandler implements CreateOrderHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private InventoryManagerInterface $inventoryManager,
        private PriceCalculatorInterface $priceCalculator,
        private CouponValidatorInterface $couponValidator,
        private TenantRepositoryInterface $tenantRepository,
        private OrderRepositoryInterface $orderRepository,
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private OrderFactory $orderFactory,
        private TransactionManagerInterface $transactionManager,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
        private ?InventoryHoldPortInterface $inventoryHoldPort = null,
        private ?CheckoutSessionPortInterface $checkoutSessionPort = null,
    ) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            return $this->processOrder($command);
        });
    }

    private function processOrder(CreateOrderCommand $command): CreateOrderResult
    {
        $user = $this->userRepository->findById($command->customerId, $command->tenantId);
        if (! $user) {
            throw new UserNotFoundException("User {$command->customerId} not found");
        }

        $lockedProducts = $this->inventoryManager->validateAndLock(
            $command->items,
            $command->tenantId
        );

        $calculatedItems = $this->priceCalculator->calculate(
            $command->items,
            $lockedProducts
        );

        $subtotal = 0;
        foreach ($calculatedItems as $calc) {
            $subtotal += $calc['line_total'];
        }

        $discountAmount = 0;
        $couponCode = null;
        $coupon = null;

        if (! empty($command->couponCode)) {
            $tenant = $this->tenantRepository->findById($command->tenantId);
            if ($tenant === null) {
                throw new TenantNotFoundException("Tenant {$command->tenantId} not found");
            }

            $coupon = $this->couponValidator->validate(
                $command->couponCode,
                $user,
                $subtotal,
                $tenant
            );

            $discountAmount = $coupon->calculateDiscountUnsafe($subtotal);
            $this->couponValidator->redeem($coupon, $user, null);
            $couponCode = $coupon->code;
        }

        $total = max(0, $subtotal - $discountAmount);
        $orderNumber = $this->orderRepository->generateOrderNumber($command->tenantId);

        $orderEntity = $this->orderFactory->create(
            tenantId: $command->tenantId,
            customerId: $user->id(),
            orderNumber: $orderNumber,
            customerName: $user->name(),
            customerEmail: $user->email(),
            currency: $command->currency,
            subtotal: $subtotal,
            total: (float) $total,
            items: $calculatedItems,
            customerPhone: $command->customerPhone,
            customerAddress: $command->customerAddress,
            customerNotes: $command->customerNotes,
            deliveryType: $command->deliveryType,
            paymentMethod: $command->paymentMethod,
            discountAmount: (float) $discountAmount,
            couponCode: $couponCode
        );

        $persistedOrder = $this->orderRepository->save($orderEntity);

        if ($coupon && $persistedOrder->id) {
            $this->couponValidator->linkToOrder($coupon, null, $persistedOrder->id);
        }

        $this->eventDispatcher->dispatch(new OrderCreated(
            orderId: $persistedOrder->id,
            tenantId: $command->tenantId,
            orderNumber: $persistedOrder->orderNumber,
            customerName: $persistedOrder->customerName,
            total: (float) $total,
            status: 'pending'
        ));

        $this->logger->info('Order created via Command Bus', $this->sanitizePayload([
            'order_id' => $persistedOrder->id,
            'tenant_id' => $command->tenantId,
            'total' => $total,
        ], $this->scrubber));

        if ($command->sessionId !== null) {
            // STEP 10: RELEASE INVENTORY HOLD
            if ($this->inventoryHoldPort !== null) {
                // Note: In strict hexagonal, the Handler shouldn't call Eloquent directly.
                // Since the Port lacks a deleteBySession() method, we release it directly here as a practical bridge
                // to avoid migrating the interface in this atomic step.
                InventoryHold::where('session_id', $command->sessionId)
                    ->where('tenant_id', $command->tenantId)
                    ->delete();
            }

            // STEP 11: MARK CHECKOUT SESSION AS CONVERTED (Prevent Abandoned Cart False Positives)
            if ($this->checkoutSessionPort !== null) {
                $this->checkoutSessionPort->markAsConverted($command->tenantId, $command->sessionId);
            }
        }

        return CreateOrderResult::fromEntity($persistedOrder);
    }
}
