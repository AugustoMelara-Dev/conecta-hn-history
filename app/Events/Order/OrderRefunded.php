<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderRefunded - Semantic Event for Refund Processing
 *
 * LISTENERS:
 *   - SendRefundConfirmationEmailListener
 *   - UpdateRevenueAnalyticsListener
 *   - RestoreInventoryIfApplicableListener
 */
class OrderRefunded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    public function __construct(
        public readonly Order $order,
        public readonly int $refundAmountCents,
        public readonly string $refundTransactionId,
        public readonly bool $isPartial = false,
        public readonly ?string $reason = null,
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
        return 'order.refunded';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'refund_amount_formatted' => 'L ' . number_format($this->refundAmountCents / 100, 2),
            'is_partial' => $this->isPartial,
            'refunded_at' => now()->toIso8601String(),
            'tenant_id' => $this->tenantId,
        ];
    }

    /**
     * Check if this was a partial refund.
     */
    public function isPartialRefund(): bool
    {
        return $this->isPartial;
    }
}
