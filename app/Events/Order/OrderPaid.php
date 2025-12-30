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
 * OrderPaid - Semantic Event for Payment Confirmation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEMANTIC EVENT: Single-Purpose, Single-Responsibility                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  WHY NOT "OrderStatusUpdated"?                                          ║
 * ║    Generic events create "God Listeners" with complex conditionals:     ║
 * ║                                                                          ║
 * ║    // BAD: God Listener                                                 ║
 * ║    if ($event->status === 'paid') { sendInvoice(); }                   ║
 * ║    elseif ($event->status === 'shipped') { sendTracking(); }           ║
 * ║    elseif ($event->status === 'cancelled') { issueRefund(); }          ║
 * ║                                                                          ║
 * ║    // GOOD: Single-Purpose Listeners                                    ║
 * ║    OrderPaid -> SendInvoiceListener                                     ║
 * ║    OrderShipped -> SendTrackingListener                                 ║
 * ║    OrderCancelled -> IssueRefundListener                                ║
 * ║                                                                          ║
 * ║  LISTENERS FOR THIS EVENT:                                              ║
 * ║    - GenerateInvoicePdfListener                                         ║
 * ║    - SendPaymentReceiptEmailListener                                    ║
 * ║    - UpdateRevenueAnalyticsListener                                     ║
 * ║    - NotifyFulfillmentTeamListener                                      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class OrderPaid implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    public function __construct(
        public readonly Order $order,
        public readonly string $transactionId,
        public readonly string $paymentMethod,
        public readonly int $amountPaidCents,
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
        return 'order.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount_formatted' => 'L ' . number_format($this->amountPaidCents / 100, 2),
            'payment_method' => $this->paymentMethod,
            'paid_at' => now()->toIso8601String(),
            'tenant_id' => $this->tenantId,
        ];
    }
}
