<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\InventoryManager;
use Illuminate\Support\Facades\Log;

/**
 * OrderObserver - Event-Driven Inventory Restoration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  OBSERVER PATTERN: Decoupled inventory logic from controller            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * RESPONSIBILITIES:
 * - Listen to Order lifecycle events (updated, deleted)
 * - Trigger automatic inventory restoration on cancellation/deletion
 * - Maintain idempotency guards to prevent double-restocking
 *
 * SEPARATION OF CONCERNS:
 * OrderController remains unaware of inventory restoration logic.
 * It simply calls $order->update() or $order->delete(), and this
 * observer automatically handles the inventory side effects.
 */
class OrderObserver
{
    /**
     * Inventory management service.
     */
    protected InventoryManager $inventoryManager;

    /**
     * Inject the InventoryManager via constructor.
     */
    public function __construct(InventoryManager $inventoryManager)
    {
        $this->inventoryManager = $inventoryManager;
    }

    /**
     * Handle the Order "updated" event.
     *
     * Triggers inventory restoration when order status changes to 'cancelled'.
     *
     * IDEMPOTENCY GUARDS:
     * 1. Only fires if 'status' field actually changed
     * 2. Only fires if new status is 'cancelled'
     * 3. Only fires if previous status was NOT 'cancelled' (prevents double-restock)
     */
    public function updated(Order $order): void
    {
        // Guard 1: Only proceed if status field was changed
        if (! $order->isDirty('status')) {
            return;
        }

        // Guard 2: Only trigger on transition TO 'cancelled'
        if ($order->status !== Order::STATUS_CANCELLED) {
            return;
        }

        // Guard 3: Prevent double-restock if already was cancelled
        $previousStatus = $order->getOriginal('status');
        if ($previousStatus === Order::STATUS_CANCELLED) {
            Log::debug("OrderObserver: Skipped restock (order was already cancelled)", [
                'order_id' => $order->id,
            ]);
            return;
        }

        // All guards passed - restore inventory
        Log::info("OrderObserver: Order cancelled, restoring inventory", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'previous_status' => $previousStatus,
        ]);

        $this->inventoryManager->restock($order);
    }

    /**
     * Handle the Order "deleted" event.
     *
     * Triggers inventory restoration when an active order is deleted.
     *
     * IDEMPOTENCY GUARD:
     * If the order was already 'cancelled' at deletion time, its stock
     * was already returned via the 'updated' event. We must NOT restore again.
     */
    public function deleted(Order $order): void
    {
        // Guard: If order was cancelled, stock already restored - skip
        if ($order->status === Order::STATUS_CANCELLED) {
            Log::debug("OrderObserver: Skipped restock on delete (order was already cancelled)", [
                'order_id' => $order->id,
            ]);
            return;
        }

        // Order was active (pending, processing, etc.) when deleted
        // Restore the inventory that was consumed
        Log::info("OrderObserver: Active order deleted, restoring inventory", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status_at_deletion' => $order->status,
        ]);

        $this->inventoryManager->restock($order);
    }

    /**
     * Handle the Order "restored" event (SoftDeletes support).
     *
     * Triggers inventory consumption when a previously deleted order is restored.
     *
     * SCENARIO:
     * 1. Admin deletes order (stock restored via deleted event)
     * 2. Admin restores order (stock must be consumed again)
     *
     * Without this handler, restored orders would have "free" inventory.
     *
     * NOTE: If insufficient stock exists, InventoryManager::consume()
     * will throw ValidationException, aborting the restoration.
     */
    public function restored(Order $order): void
    {
        // If order is in a cancelled state, it should remain cancelled
        // and no inventory should be consumed
        if ($order->status === Order::STATUS_CANCELLED) {
            Log::debug("OrderObserver: Skipped consumption on restore (order is cancelled)", [
                'order_id' => $order->id,
            ]);
            return;
        }

        // Order was active when deleted, now restored - consume inventory again
        Log::info("OrderObserver: Order restored, consuming inventory", [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
        ]);

        // Delegate to InventoryManager (throws exception if stock insufficient)
        $this->inventoryManager->consume($order);
    }
}

