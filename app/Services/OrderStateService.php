<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
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
class OrderStateService
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
     */
    public function __construct(
        private readonly InventoryManager $inventoryManager
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
     * DELEGATION: Passes entire Order aggregate to InventoryManager.
     * The manager handles item iteration, validation, and stock restoration.
     */
    private function onCancelled(Order $order, array &$updateData): void
    {
        $updateData['cancelled_at'] = now();

        // ═══════════════════════════════════════════════════════════════════════
        // INVENTORY RESTORATION: Delegate to Domain Authority
        // ═══════════════════════════════════════════════════════════════════════
        // The InventoryManager has the atomic restock(Order) method that handles:
        // - Item iteration
        // - Product validation (deleted, unmanaged)
        // - Stock increment with state recovery
        //
        // Guard: Only call if order has items
        if ($order->items()->exists()) {
            $this->inventoryManager->restock($order);
        }
    }

    /**
     * Dispatch real-time broadcasting event.
     */
    private function dispatchStatusEvent(Order $order, string $oldStatus, string $newStatus): void
    {
        // Event dispatching is handled by OrderObserver's saved event
        // which implements ShouldHandleEventsAfterCommit for transactional safety.
        // If you want explicit control, dispatch here:
        //
        // event(new \App\Events\OrderStatusUpdated($order, $oldStatus, $newStatus));
    }
}
