<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * PaymentFailed - Domain Event for Failed Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * Implements DomainEvent (canonical) via Bridge pattern.
 */
final readonly class PaymentFailed implements DomainEvent, ShouldDispatchAfterCommit
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

        /** W-01: DomainEvent metadata — populated by Outbox relay or Handler */
        public string $eventId = '',
        public string $correlationId = '',
    ) {}

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function eventId(): string        { return $this->eventId; }
    public function correlationId(): string  { return $this->correlationId; }
    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return $this->transactionId; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    /** Bridge: delegates to toPayload(). */
    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    /** Bridge: delegates to fromPayload(). */
    public static function fromPrimitives(array $primitives): self
    {
        return static::fromPayload($primitives);
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            transactionId:     (string) ($payload['transaction_id']    ?? ''),
            providerReference: (string) ($payload['provider_reference'] ?? ''),
            reason:            (string) ($payload['reason']             ?? ''),
            tenantId:          (string) ($payload['tenant_id']          ?? ''),
            failureCode:       isset($payload['failure_code']) ? (string) $payload['failure_code'] : null,
            occurredAt:        new \DateTimeImmutable($payload['occurred_at'] ?? 'now'),
            eventId:           (string) ($payload['event_id']           ?? ''),
            correlationId:     (string) ($payload['correlation_id']     ?? ''),
        );
    }

    public function toPayload(): array
    {
        return [
            'schema_version'     => self::SCHEMA_VERSION,
            'transaction_id'     => $this->transactionId,
            'provider_reference' => $this->providerReference,
            'reason'             => $this->reason,
            'failure_code'       => $this->failureCode,
            'tenant_id'          => $this->tenantId,
            'occurred_at'        => $this->occurredAt->format(\DateTimeInterface::ATOM),
            'event_id'           => $this->eventId,
            'correlation_id'     => $this->correlationId,
        ];
    }
}
