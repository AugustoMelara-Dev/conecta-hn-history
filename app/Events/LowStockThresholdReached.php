<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LowStockThresholdReached - Real-time Low Stock Alert
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EVENT-DRIVEN ALERTING: PROACTIVE INVENTORY MANAGEMENT                  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * TRIGGERED WHEN:
 * A product's stock_quantity falls at or below its configured low_stock_threshold
 * after an order is placed.
 *
 * LISTENERS CAN:
 * - Send email notification to tenant owner
 * - Push notification to mobile app
 * - Create database alert for dashboard
 * - Trigger webhook to external inventory system
 *
 * CONTEXT:
 * Dispatched from OrderController::createOrderWithinTransaction()
 * immediately after atomic stock decrement.
 */
class LowStockThresholdReached
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The product that reached low stock threshold.
     */
    public Product $product;

    /**
     * The new stock quantity after decrement.
     */
    public int $newStockQuantity;

    /**
     * The configured threshold value.
     */
    public int $threshold;

    /**
     * Create a new event instance.
     */
    public function __construct(Product $product, int $newStockQuantity, int $threshold)
    {
        $this->product = $product;
        $this->newStockQuantity = $newStockQuantity;
        $this->threshold = $threshold;
    }
}
