<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * PaymentReported - Domain Event for Manual Payment Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCHEMA VERSIONED: Enables future upcasting without breaking consumers.  ║
 * ║  TRANSACTIONAL: Implements ShouldDispatchAfterCommit for Outbox safety.  ║
 * ║  TYPE SAFE: Uses string IDs (UUID/ULID compatible).                      ║
 * ║  TEMPORAL ACCURACY: occurredAt is immutable, set at creation time.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 * Implements DomainEvent (canonical) via Bridge pattern.
 */
final readonly class PaymentReported implements DomainEvent, ShouldDispatchAfterCommit
{
    public const SCHEMA_VERSION = 4;

    public function __construct(
        public string $paymentId,
        public string $tenantId,
        public int $amountInCents,
        public string $currency,
        public string $reference,
        public ?string $idempotencyKey = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
        public int $schemaVersion = self::SCHEMA_VERSION,
        /** W-01: DomainEvent metadata — populated by Outbox relay or Handler */
        public string $eventId = '',
        public string $correlationId = '',
    ) {}

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function eventId(): string        { return $this->eventId; }
    public function correlationId(): string  { return $this->correlationId; }
    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return $this->paymentId; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }

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
     * Reconstruct event from safe payload.
     *
     * Essential for eliminating "Metadata Bomb" (extra keys) and
     * resolving Case Mismatch (snake vs camel).
     */
    public static function fromPayload(array $payload): self
    {
        $occurredAt = isset($payload['occurred_at'])
            ? new DateTimeImmutable($payload['occurred_at'])
            : new DateTimeImmutable();

        return new self(
            paymentId:      (string) ($payload['payment_id']      ?? ''),
            tenantId:       (string) ($payload['tenant_id']       ?? ''),
            amountInCents:  (int)    ($payload['amount_in_cents'] ?? 0),
            currency:       (string) ($payload['currency']        ?? 'HNL'),
            reference:      (string) ($payload['reference']       ?? ''),
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            occurredAt:     $occurredAt,
            schemaVersion:  (int)    ($payload['schema_version']  ?? self::SCHEMA_VERSION),
            eventId:        (string) ($payload['event_id']        ?? ''),
            correlationId:  (string) ($payload['correlation_id']  ?? ''),
        );
    }

    /**
     * Serialize to safe payload for Outbox storage.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version'  => $this->schemaVersion,
            'payment_id'      => $this->paymentId,
            'tenant_id'       => $this->tenantId,
            'amount_in_cents' => $this->amountInCents,
            'currency'        => $this->currency,
            'reference'       => $this->reference,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at'     => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id'        => $this->eventId,
            'correlation_id'  => $this->correlationId,
        ];
    }
}
