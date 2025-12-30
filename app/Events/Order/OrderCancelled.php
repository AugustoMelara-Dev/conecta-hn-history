<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderCancelled - Semantic Event for Order Voiding
 *
 * LISTENERS:
 *   - SendCancellationEmailListener
 *   - RestoreInventoryListener
 *   - InitiateRefundListener
 *   - UpdateAnalyticsListener
 */
class OrderCancelled implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    public function __construct(
        public readonly Order $order,
        public readonly string $reason,
        public readonly ?User $cancelledBy = null,
        public readonly bool $autoRefund = false,
    ) {
        $this->tenantId = $order->tenant_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'reason' => $this->reason,
            'auto_refund' => $this->autoRefund,
            'cancelled_at' => now()->toIso8601String(),
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * Check if automatic refund should be processed.
     */
    public function shouldAutoRefund(): bool
    {
        return $this->autoRefund && $this->order->isPaid();
    }
}
