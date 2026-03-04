<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * PaymentReceived - Domain Event for Successful Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class PaymentReceived implements ShouldDispatchAfterCommit
{
    public function __construct(
        /** Internal transaction reference */
        public string $transactionId,

        /** External provider reference (stripe_pi_xxx, paypal_xxx) */
        public string $providerReference,

        /** Amount in cents (integer for precision) */
        public int $amount,

        /** ISO 4217 currency code */
        public string $currency,

        /** Payer's email for notification */
        public ?string $payerEmail = null,

        /** Tenant ID for multi-tenant context (required for A3 traceability) */
        public string $tenantId,

        /** Timestamp locked at instantiation for deterministic replayability */
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payer_email' => $this->payerEmail,
            'tenant_id'          => $this->tenantId,
            'occurred_at'        => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
