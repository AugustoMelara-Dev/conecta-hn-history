<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Sales\Factories\OrderFactory;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Exceptions\TenantNotFoundException;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Services\CouponValidatorService;
use App\Services\InventoryManager;
use App\Services\PriceCalculatorService;

/**
 * CreateOrderHandler - Pure Business Logic for Order Creation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TYPE SAFETY ENFORCED: No Array Persistence                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Uses OrderFactory for aggregation.                                   ║
 * ║  - Uses Repository->save(OrderEntity).                                  ║
 * ║  - Transactional via TransactionManagerInterface.                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CreateOrderHandler implements CreateOrderHandlerInterface
{
    use LogSanitizer;

    public function __construct(
        private InventoryManager $inventoryManager,
        private PriceCalculatorService $priceCalculator,
        private CouponValidatorService $couponValidator,
        private TenantRepositoryInterface $tenantRepository,
        private OrderRepositoryInterface $orderRepository,
        private \App\Domain\Identity\Repositories\UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private OrderFactory $orderFactory,
        private TransactionManagerInterface $transactionManager, // Injected for Atomicity,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    public function handle(CreateOrderCommand $command): CreateOrderResult
    {
        return $this->transactionManager->transaction(function () use ($command) {
            return $this->processOrder($command);
        });
    }

    private function processOrder(CreateOrderCommand $command): CreateOrderResult
    {
        // STEP 0: RESOLVE CUSTOMER
        $user = $this->userRepository->findById($command->customerId);
        if (! $user) {
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

        // STEP 4: COUPON VALIDATION
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
            // Note: Redemption should ideally happen inside transaction implicitly or here explicitly
            $this->couponValidator->redeem($coupon, $user, null);
            $couponCode = $coupon->code;
        }

        $total = max(0, $subtotal - $discountAmount);

        // STEP 5: GENERATE ORDER NUMBER
        $orderNumber = $this->orderRepository->generateOrderNumber($command->tenantId);

        // STEP 6: FACTORY INSTANTIATION (Domain Aggregation)
        $orderEntity = $this->orderFactory->create(
            tenantId: $command->tenantId,
            customerId: $user->id,
            orderNumber: $orderNumber,
            customerName: $user->name,
            customerEmail: $user->email->value, // Value Object Access
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

        // STEP 7: PERSISTENCE (Type Safe)
        // --------------------------------------------------------------------------
        // @TODO [ARCH-DEBT]: Refactorización de Persistencia Requerida
        // --------------------------------------------------------------------------
        // VIOLACIÓN DETECTADA: Aunque se usa OrderEntity, el flujo de persistencia
        // ideal debería ser repo->save(OrderAggregate) con mapeo interno en infraestructura.
        // ESTADO: Mitigado parcialmente con Entity wrapper.
        // --------------------------------------------------------------------------
        $this->orderRepository->save($orderEntity);

        // STEP 8: LINK COUPON TO ORDER (After ID generated/saved)
        if ($coupon && $orderEntity->id) {
            // Assuming $orderEntity->id is populated by repository save (reference pass or return)
            // Ideally save() should return the fresh entity with ID
            $this->couponValidator->linkToOrder($coupon, $command->requestorId, $orderEntity->id);
        }

        // STEP 9: DISPATCH EVENT
        $this->eventDispatcher->dispatch(new \App\Domain\Sales\Events\OrderCreated(
            orderId: $orderEntity->id,
            tenantId: $command->tenantId, // or $orderEntity->tenantId if available
            orderNumber: $orderEntity->orderNumber->toString(), // Assuming VO or string
            customerName: $orderEntity->customerName,
            total: (float) $total,
            status: 'pending' // Default status for new orders
        ));

        $this->logger->info('Order created via Command Bus', $this->sanitizePayload([
            'order_id' => $orderEntity->id,
            'tenant_id' => $command->tenantId,
            'total' => $total,
        ], $this->scrubber));

        return CreateOrderResult::fromEntity($orderEntity);
    }
}
