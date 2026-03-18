<?php

declare(strict_types=1);

namespace App\Application\Sales\Handlers;

use App\Application\Sales\Commands\CancelOrderCommand;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Sales\Events\OrderCancelled;
use App\Domain\Sales\Exceptions\OrderNotFoundException;
use App\Domain\Sales\Repositories\OrderRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

/**
 * CancelOrderHandler - Command Handler for Order Cancellation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  RICH DOMAIN MODEL: Delegates invariant validation to Order entity.     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO ProductRepository (inventory restored via async listener)          ║
 * ║  - NO PaymentService (refund initiated via async listener)               ║
 * ║  - ONLY responsibility: persist cancellation + dispatch event            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class CancelOrderHandler
{
    use LogSanitizer;

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle order cancellation command.
     *
     * @throws OrderNotFoundException If order not found or tenant mismatch
     * @throws \App\Domain\Sales\Exceptions\OrderCannotBeCancelledException
     */
    public function handle(CancelOrderCommand $command): void
    {
        // 1. Load Order from Repository
        $order = $this->orderRepository->findById($command->orderId);

        // 2. Guard: Order Existence
        if ($order === null) {
            throw new OrderNotFoundException("Order not found: {$command->orderId}");
        }

        // 3. SECURITY GUARD: Tenant Ownership Verification (IDOR Protection)
        if ($order->tenant_id !== $command->tenantId) {
            $this->logger->warning('SECURITY: Cross-tenant order cancellation attempt', [
                'requested_order_id' => $command->orderId,
                'requesting_tenant_id' => $command->tenantId,
                'actual_tenant_id' => $order->tenant_id,
            ]);
            throw new OrderNotFoundException("Order not found: {$command->orderId}");
        }

        // 4. DELEGATE TO DOMAIN: Let Order protect its own invariants
        $order->cancel($command->reason, $command->userId);

        // 5. Persist via Repository
        $this->orderRepository->save($order);

        // 6. Dispatch Domain Event (Transactional Outbox)
        // Includes items data for async inventory restoration
        $this->eventDispatcher->dispatch(OrderCancelled::create(
            tenantId: $order->tenant_id,
            orderId: $order->id,
            orderNumber: $order->order_number,
            reason: $command->reason,
            cancelledById: $command->userId,
            wasPaid: $order->wasPaid(),
        ));

        // 7. Audit Log (PII Scrubbed)
        $this->logger->info('Order cancelled successfully', $this->sanitizePayload([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'tenant_id' => $command->tenantId,
            'reason' => $command->reason,
            'cancelled_by' => $command->userId,
        ], $this->scrubber));
    }
}
