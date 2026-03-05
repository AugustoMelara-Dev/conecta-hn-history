<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * PaymentReceived - Domain Event for Successful Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 *
 * A6/GDPR: payer email has been removed from the event payload.
 * Events are stored indefinitely in the Outbox — PII must never persist.
 * Consumers requiring payer contact info must query the user repository
 * using $transactionId as the correlation key.
 */
final readonly class PaymentReceived implements ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        /** Internal transaction reference */
        public string $transactionId,

        /** External provider reference (stripe_pi_xxx, paypal_xxx) */
        public string $providerReference,

        /** Amount in cents (integer for precision) */
        public int $amount,

        /** ISO 4217 currency code */
        public string $currency,

        /** Tenant ID for multi-tenant context (required for A3 traceability) */
        public string $tenantId,

        /** Timestamp locked at instantiation for deterministic replayability */
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
    ) {}

    /**
     * Reconstruct event from safe payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            transactionId:     (string) ($payload['transaction_id']    ?? ''),
            providerReference: (string) ($payload['provider_reference'] ?? ''),
            amount:            (int)    ($payload['amount']             ?? 0),
            currency:          (string) ($payload['currency']           ?? ''),
            tenantId:          (string) ($payload['tenant_id']          ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
        );
    }

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * A6/GDPR: payer_email intentionally excluded from payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version'     => self::SCHEMA_VERSION,
            'transaction_id'     => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'amount'             => $this->amount,
            'currency'           => $this->currency,
            'tenant_id'          => $this->tenantId,
            'occurred_at'        => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
