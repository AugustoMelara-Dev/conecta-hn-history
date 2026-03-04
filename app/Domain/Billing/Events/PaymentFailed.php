<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * PaymentFailed - Domain Event for Failed Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 */
final readonly class PaymentFailed implements ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        /** Internal transaction reference */
        public string $transactionId,

        /** External provider reference */
        public string $providerReference,

        /** Human-readable failure reason */
        public string $reason,

        /** Tenant ID for multi-tenant context (required for A3 traceability) */
        public string $tenantId,

        /** Provider-specific failure code */
        public ?string $failureCode = null,

        /** Timestamp locked at instantiation for deterministic replayability */
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            transactionId: $payload['transaction_id'],
            providerReference: $payload['provider_reference'],
            reason: $payload['reason'],
            tenantId: $payload['tenant_id'],
            failureCode: $payload['failure_code'] ?? null,
            occurredAt: new \DateTimeImmutable($payload['occurred_at'])
        );
    }

    public function toPayload(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'reason' => $this->reason,
            'failure_code' => $this->failureCode,
            'tenant_id'          => $this->tenantId,
            'occurred_at'        => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
