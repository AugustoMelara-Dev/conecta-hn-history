<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * PaymentReceived - Domain Event for Successful Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * Implements DomainEvent (canonical) via Bridge pattern (W-01).
 * Uses HasEventIdentity (W-03) for auto-generated eventId/correlationId.
 *
 * A6/GDPR: payer email has been removed from the event payload.
 * Events are stored indefinitely in the Outbox — PII must never persist.
 * Consumers requiring payer contact info must query the user repository
 * using $transactionId as the correlation key.
 */
final readonly class PaymentReceived implements DomainEvent, ShouldBeStoredInOutbox, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public string $transactionId,
        public string $providerReference,
        public int $amount,
        public string $currency,
        public string $tenantId,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function tenantId(): mixed
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return $this->transactionId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    // ─── ShouldBeStoredInOutbox ───────────────────────────────────────────────
    public function getAggregateId(): ?string
    {
        return $this->transactionId;
    }

    public function getAggregateType(): ?string
    {
        return 'Payment';
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            transactionId: (string) ($payload['transaction_id'] ?? ''),
            providerReference: (string) ($payload['provider_reference'] ?? ''),
            amount: (int) ($payload['amount'] ?? 0),
            currency: (string) ($payload['currency'] ?? ''),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable,
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    /**
     * A6/GDPR: payer_email intentionally excluded from payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'transaction_id' => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tenant_id' => $this->tenantId,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
