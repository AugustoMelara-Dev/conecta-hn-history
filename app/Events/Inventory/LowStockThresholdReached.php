<?php

declare(strict_types=1);

namespace App\Events\Inventory;

use App\Models\Product;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * LowStockThresholdReached - Transaction-Safe Inventory Alert
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PROACTIVE INVENTORY MANAGEMENT                                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  TRIGGERED WHEN:                                                        ║
 * ║    Product stock_quantity falls at or below low_stock_threshold         ║
 * ║    after an order is placed.                                            ║
 * ║                                                                          ║
 * ║  LISTENERS CAN:                                                         ║
 * ║    - Send email notification to tenant owner                           ║
 * ║    - Push notification to mobile app                                   ║
 * ║    - Create database alert for dashboard                               ║
 * ║    - Trigger webhook to external inventory system                      ║
 * ║                                                                          ║
 * ║  TRANSACTION SAFETY:                                                    ║
 * ║    Implements ShouldDispatchAfterCommit because this event is          ║
 * ║    dispatched inside the order creation transaction. We must           ║
 * ║    ensure the stock decrement is COMMITTED before listeners query.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class LowStockThresholdReached implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly int $currentStock,
        public readonly int $threshold,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->product->tenant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'inventory.low_stock';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_sku' => $this->product->sku,
            'current_stock' => $this->currentStock,
            'threshold' => $this->threshold,
            'is_critical' => $this->currentStock === 0,
        ];
    }

    /**
     * Check if stock is completely depleted.
     */
    public function isOutOfStock(): bool
    {
        return $this->currentStock === 0;
    }

    /**
     * Get the severity level for alerting.
     */
    public function getSeverity(): string
    {
        if ($this->currentStock === 0) {
            return 'critical';
        }

        if ($this->currentStock <= $this->threshold / 2) {
            return 'warning';
        }

        return 'info';
    }
}
