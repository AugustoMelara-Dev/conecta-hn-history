<?php

declare(strict_types=1);

namespace App\Events\Payment;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PaymentApproved - Transaction-Safe Payment Confirmation Event
 *
 * Dispatched when a payment is successfully processed by the gateway.
 * Triggers order fulfillment workflows.
 *
 * LISTENERS:
 *   - MarkOrderAsPaid
 *   - SendPaymentReceiptEmail
 *   - UpdateAccountingLedger
 *   - TriggerFulfillmentWorkflow
 */
class PaymentApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $transactionId,
        public readonly string $gatewayName,
        public readonly int $amountCents,
    ) {}

    /**
     * Get the amount in formatted currency.
     */
    public function getFormattedAmount(): string
    {
        return 'L ' . number_format($this->amountCents / 100, 2);
    }
}
