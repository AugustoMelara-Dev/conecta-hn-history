<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Sales\Contracts\OrderStateServiceInterface;
use App\Contracts\Events\DomainEventBus;
use App\Events\Order\OrderCancelled;
use App\Contracts\InventoryManagerInterface;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Support\Facades\DB;

/**
 * OrderStateService - Finite State Machine for Order Lifecycle
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRICT TOPOLOGY: Illegal transitions are physically rejected           ║
 * ║  SIDE-EFFECTS: Automatic inventory/timestamp management                 ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * VALID WORKFLOW:
 *   pending → confirmed → processing → ready → delivered
 *                                           ↘ cancelled (from any except delivered)
 *
 * RESPONSIBILITIES:
 * 1. Validate state transitions against FSM topology
 * 2. Execute side-effects (restock on cancel, timestamp on deliver)
 * 3. Dispatch broadcasting events for real-time updates
 */
class OrderStateService implements OrderStateServiceInterface
{
    /**
     * FSM TOPOLOGY: Defines allowed transitions from each state.
     *
     * Key = current status
     * Value = array of allowed next statuses
     */
    private const TRANSITION_MAP = [
        Order::STATUS_PENDING => [
            Order::STATUS_CONFIRMED,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_CONFIRMED => [
            Order::STATUS_PROCESSING,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_READY,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_READY => [
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_DELIVERED => [
            // Terminal state - no further transitions allowed
        ],
        Order::STATUS_CANCELLED => [
            // Terminal state - no further transitions allowed
        ],
    ];

    /**
     * Inject dependencies via constructor.
     *
     * DECOUPLING: DomainEventBus abstracts Laravel's event dispatcher.
     * This allows pure domain unit tests with InMemoryEventBus.
     */
    public function __construct(
        private readonly InventoryManagerInterface $inventoryManager,
        private readonly DomainEventBus $eventBus,
    ) {}

    /**
     * Transition an order to a new status.
     *
     * @throws InvalidOrderTransitionException If transition is not allowed
     */
    public function transition(Order $order, string $newStatus): Order
    {
        $currentStatus = $order->status;

        // Validate transition is allowed
        $this->validateTransition($currentStatus, $newStatus);

        // Execute within transaction for atomicity
        return DB::transaction(function () use ($order, $newStatus, $currentStatus) {
            $updateData = ['status' => $newStatus];

            // ═══════════════════════════════════════════════════════════════════════
            // SIDE-EFFECTS: Execute based on target status
            // ═══════════════════════════════════════════════════════════════════════

            match ($newStatus) {
                Order::STATUS_CONFIRMED => $this->onConfirmed($order, $updateData),
                Order::STATUS_PROCESSING => $this->onProcessing($order, $updateData),
                Order::STATUS_READY => $this->onReady($order, $updateData),
                Order::STATUS_DELIVERED => $this->onDelivered($order, $updateData),
                Order::STATUS_CANCELLED => $this->onCancelled($order, $updateData),
                default => null,
            };

            // Persist the state change
            $order->update($updateData);

            // ═══════════════════════════════════════════════════════════════════════
            // EVENT SOURCING: Record immutable audit trail
            // ═══════════════════════════════════════════════════════════════════════
            OrderEvent::record($order, 'status_changed', [
                'from' => $currentStatus,
                'to' => $newStatus,
                'triggered_by' => 'fsm_transition',
            ], auth()->id());

            // Dispatch real-time event (handled by OrderObserver or here)
            $this->dispatchStatusEvent($order, $currentStatus, $newStatus);

            return $order->fresh();
        });
    }

    /**
     * Validate that a transition is allowed by the FSM topology.
     *
     * @throws InvalidOrderTransitionException
     */
    public function validateTransition(string $from, string $to): void
    {
        $allowedTransitions = self::TRANSITION_MAP[$from] ?? [];

        if (! in_array($to, $allowedTransitions, true)) {
            throw new InvalidOrderTransitionException($from, $to, $allowedTransitions);
        }
    }

    /**
     * Check if a transition is valid (without throwing).
     */
    public function canTransition(string $from, string $to): bool
    {
        $allowedTransitions = self::TRANSITION_MAP[$from] ?? [];

        return in_array($to, $allowedTransitions, true);
    }

    /**
     * Get allowed transitions from a given status.
     */
    public function getAllowedTransitions(string $status): array
    {
        return self::TRANSITION_MAP[$status] ?? [];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SIDE-EFFECT HANDLERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Side-effects when order is confirmed.
     */
    private function onConfirmed(Order $order, array &$updateData): void
    {
        $updateData['confirmed_at'] = now();
    }

    /**
     * Side-effects when order enters processing.
     */
    private function onProcessing(Order $order, array &$updateData): void
    {
        $updateData['processing_started_at'] = now();
    }

    /**
     * Side-effects when order is ready for pickup/delivery.
     */
    private function onReady(Order $order, array &$updateData): void
    {
        $updateData['ready_at'] = now();
    }

    /**
     * Side-effects when order is delivered (terminal success).
     */
    private function onDelivered(Order $order, array &$updateData): void
    {
        $updateData['delivered_at'] = now();
    }

    /**
     * Side-effects when order is cancelled (terminal failure).
     *
     * ARCHITECTURE NOTE:
     * Inventory restoration is handled EXCLUSIVELY by OrderObserver::updated().
     * This prevents the "Double Restock" race condition where both the Service
     * and Observer attempt to restore stock simultaneously.
     *
     * This method ONLY sets the timestamp. The Observer will detect the
     * status change to 'cancelled' and invoke InventoryManager::restock().
     */
    private function onCancelled(Order $order, array &$updateData): void
    {
        $updateData['cancelled_at'] = now();

        // ═══════════════════════════════════════════════════════════════════════
        // INVENTORY RESTORATION: Delegated to OrderObserver (Single Source of Truth)
        // ═══════════════════════════════════════════════════════════════════════
        // DO NOT call inventoryManager->restock() here!
        // The OrderObserver::updated() method will handle this when:
        // 1. $order->update($updateData) is called
        // 2. Observer detects status changed to 'cancelled'
        // 3. Observer validates guards and calls restock()
        //
        // This eliminates the CRITICAL race condition where stock was
        // being restored twice (once here, once in Observer).
    }

    /**
     * Dispatch semantic domain events based on the new status.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  SEMANTIC EVENTS: Replace Generic OrderStatusUpdated                    ║
     * ╠══════════════════════════════════════════════════════════════════════════╣
     * ║                                                                          ║
     * ║  BEFORE (Anti-Pattern):                                                 ║
     * ║    event(new OrderStatusUpdated($order, $old, $new));                   ║
     * ║    → God Listener with 10 if-else branches                              ║
     * ║                                                                          ║
     * ║  AFTER (Semantic):                                                      ║
     * ║    OrderConfirmed → ConfirmationEmailListener                           ║
     * ║    OrderCancelled → RestoreInventoryListener                            ║
     * ║    OrderDelivered → RequestReviewListener                               ║
     * ║                                                                          ║
     * ║  Each event has single-responsibility listeners.                       ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     */
    private function dispatchStatusEvent(Order $order, string $oldStatus, string $newStatus): void
    {
        $event = match ($newStatus) {
            Order::STATUS_CANCELLED => OrderCancelled::create(
                tenantId: $order->tenant_id,
                orderId: $order->id,
                orderNumber: $order->order_number,
                reason: 'status_transition',
                cancelledById: auth()->id(),
                wasPaid: $order->isPaid(),
            ),
            Order::STATUS_DELIVERED => \App\Events\Order\OrderDelivered::create(
                tenantId: $order->tenant_id,
                orderId: $order->id,
                orderNumber: $order->order_number,
            ),
            // NOTE: OrderPaid is dispatched from PaymentService, not here
            // NOTE: OrderShipped is dispatched from ShippingService, not here
            default => null,
        };

        if ($event !== null) {
            $this->eventBus->publish($event);
        }
    }
}
