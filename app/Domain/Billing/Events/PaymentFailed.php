<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * PaymentFailed - Domain Event for Failed Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class PaymentFailed implements ShouldDispatchAfterCommit
{
    public function __construct(
        /** Internal transaction reference */
        public string $transactionId,

        /** External provider reference */
        public string $providerReference,

        /** Human-readable failure reason */
        public string $reason,

        /** Provider-specific failure code */
        public ?string $failureCode = null,

        /** Tenant ID for multi-tenant context */
        public ?string $tenantId = null,
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
            'reason' => $this->reason,
            'failure_code' => $this->failureCode,
            'tenant_id' => $this->tenantId,
            'occurred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
