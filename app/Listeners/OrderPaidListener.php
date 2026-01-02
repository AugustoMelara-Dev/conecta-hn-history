<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Broadcast\OrderPaidBroadcast;
use App\Events\Order\OrderPaid;

/**
 * OrderPaidListener - Application Layer Bridge
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APPLICATION LAYER: Reacts to Payment Confirmation                      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  FLOW:                                                                  ║
 * ║    PaymentService::confirmPayment()                                     ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    DomainEventBus->publish(OrderPaid)                                  ║
 * ║         │                                                               ║
 * ║         ▼                                                               ║
 * ║    THIS LISTENER                                                        ║
 * ║         │                                                               ║
 * ║         ├──▶ GenerateInvoicePdf                                        ║
 * ║         ├──▶ SendPaymentReceiptEmail                                   ║
 * ║         ├──▶ UpdateRevenueAnalytics                                    ║
 * ║         ├──▶ NotifyFulfillmentTeam                                     ║
 * ║         └──▶ broadcast(OrderPaidBroadcast) (UI update)                 ║
 * ║                                                                          ║
 * ║  EACH RESPONSIBILITY CAN BE A SEPARATE LISTENER:                       ║
 * ║    For maximum SRP, you can split this into:                           ║
 * ║    - GenerateInvoiceOnOrderPaid                                        ║
 * ║    - SendReceiptOnOrderPaid                                            ║
 * ║    - BroadcastOrderPaid                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class OrderPaidListener
{
    /**
     * Handle the OrderPaid domain event.
     */
    public function handle(OrderPaid $event): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // STEP 1: Generate Invoice PDF (async job recommended)
        // ─────────────────────────────────────────────────────────────────────
        // NOTE: Dispatch a job here for heavy processing
        // GenerateInvoicePdf::dispatch($event->aggregateId());

        // ─────────────────────────────────────────────────────────────────────
        // STEP 2: Send Payment Receipt Email
        // ─────────────────────────────────────────────────────────────────────
        // NOTE: Handled by OrderObserver or separate listener

        // ─────────────────────────────────────────────────────────────────────
        // STEP 3: Update Revenue Analytics
        // ─────────────────────────────────────────────────────────────────────
        logger()->info('Payment received', [
            'order_id' => $event->aggregateId(),
            'tenant_id' => $event->tenantId()->toInt(),
            'amount_cents' => $event->amountPaid->inCents(),
            'payment_method' => $event->paymentMethod,
        ]);

        // ─────────────────────────────────────────────────────────────────────
        // STEP 4: Real-Time UI Update - Broadcast to tenant dashboard
        // ─────────────────────────────────────────────────────────────────────
        broadcast(OrderPaidBroadcast::fromDomainData(
            tenantId: $event->tenantId()->toInt(),
            orderId: $event->aggregateId(),
            orderNumber: $event->orderNumber->toString(),
            amountCents: $event->amountPaid->inCents(),
            paymentMethod: $event->paymentMethod,
            paidAt: $event->occurredAt(),
        ));
    }
}
