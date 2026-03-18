<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Results\CreateOrderResult;
use App\Application\Shared\Contracts\CouponValidatorInterface;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\InventoryManagerInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Contracts\PriceCalculatorInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Inventory\Contracts\LocationReaderInterface;
use App\Domain\Sales\Events\OrderCreated;
use App\Domain\Sales\Factories\OrderFactory;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use DomainException;

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
        private LocationReaderInterface $locationReader,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
        private CapabilityManagerInterface $capabilityManager,
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
        $tenant = $this->tenantRepository->findById($command->tenantId);
        if ($tenant === null) {
            throw TenantNotFoundException::withId($command->tenantId);
        }

        $this->assertFulfillmentCapability($command);
        $this->assertValidLocation($command);

        $user = $this->userRepository->findById($command->customerId, $command->tenantId);
        if (! $user) {
            throw UserNotFoundException::withIdAndTenant($command->customerId, $command->tenantId);
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
            locationId: $command->locationId,
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
        $orderId = $persistedOrder->id
            ?? throw new \LogicException('Order repository must return a persisted order ID.');

        if ($coupon && $persistedOrder->id) {
            $this->couponValidator->linkToOrder($coupon, $user, $orderId);
        }

        $this->eventDispatcher->dispatch(new OrderCreated(
            orderId: $orderId,
            tenantId: $command->tenantId,
            orderNumber: $persistedOrder->orderNumber,
            customerName: $persistedOrder->customerName,
            total: (int) round($total),
            status: 'pending'
        ));

        $this->logger->info('Order created via Command Bus', $this->sanitizePayload([
            'order_id' => $persistedOrder->id,
            'tenant_id' => $command->tenantId,
            'total' => $total,
        ], $this->scrubber));

        if ($command->sessionId !== null) {
            if ($this->inventoryHoldPort !== null) {
                $this->inventoryHoldPort->commit(
                    $command->tenantId,
                    $command->sessionId,
                    $orderId
                );
            }

            if ($this->checkoutSessionPort !== null) {
                $this->checkoutSessionPort->markAsConverted($command->tenantId, $command->sessionId);
            }
        }

        return CreateOrderResult::fromEntity($persistedOrder);
    }

    private function assertFulfillmentCapability(CreateOrderCommand $command): void
    {
        $capability = match ($command->deliveryType) {
            'pickup' => Capability::PICKUP_ORDERS,
            'delivery' => Capability::DELIVERY_ORDERS,
            'shipping' => Capability::SHIPPING_ORDERS,
            default => throw new DomainException('Tipo de entrega no soportado.'),
        };

        if ($this->capabilityManager->hasForTenantId($command->tenantId, $capability)) {
            return;
        }

        $message = match ($command->deliveryType) {
            'pickup' => 'Este comercio no permite retiro en tienda.',
            'delivery' => 'Este comercio no ofrece entrega a domicilio.',
            'shipping' => 'Este comercio no realiza envios nacionales.',
            default => 'Tipo de entrega no soportado.',
        };

        throw new DomainException($message);
    }

    private function assertValidLocation(CreateOrderCommand $command): void
    {
        if ($command->locationId === null || $command->locationId === '') {
            throw new DomainException('Location is required to create an order.');
        }

        $locationExists = $this->locationReader->existsActiveForTenant(
            $command->locationId,
            $command->tenantId
        );

        if ($locationExists) {
            return;
        }

        throw new DomainException("Invalid or inactive location {$command->locationId}");
    }
}
