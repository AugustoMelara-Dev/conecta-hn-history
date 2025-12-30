<?php

declare(strict_types=1);

namespace App\Events\Order;

use App\Models\Order;
use App\Traits\TenantAwareEvent;
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
 * ║  SEMANTIC VS GENERIC EVENTS                                             ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  ANTI-PATTERN (Generic):                                                ║
 * ║    OrderStatusUpdated → God Listener with 10 if-else branches           ║
 * ║                                                                          ║
 * ║  CORRECT (Semantic):                                                    ║
 * ║    OrderPaid      → SendInvoiceListener (single responsibility)         ║
 * ║    OrderShipped   → SendTrackingListener                                ║
 * ║    OrderCancelled → IssueRefundListener                                 ║
 * ║                                                                          ║
 * ║  @notice $tenantId is required for Queue Middleware context switching.  ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * LISTENERS:
 *   - GenerateInvoicePdfListener
 *   - SendPaymentReceiptEmailListener
 *   - UpdateRevenueAnalyticsListener
 *   - NotifyFulfillmentTeamListener
 */
class OrderPaid implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels, TenantAwareEvent;

    public function __construct(
        public readonly Order $order,
        public readonly string $transactionId,
        public readonly string $paymentMethod,
        public readonly int $amountPaidCents,
    ) {
        $this->setTenantContext($order->tenant_id);
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

    /**
     * @warning NO PII in broadcast payload.
     */
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
