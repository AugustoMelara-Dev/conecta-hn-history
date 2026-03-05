<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;

/**
 * PaymentReported - Domain Event for Manual Payment Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SCHEMA VERSIONED: Enables future upcasting without breaking consumers.  ║
 * ║  TRANSACTIONAL: Implements ShouldDispatchAfterCommit for Outbox safety.  ║
 * ║  TYPE SAFE: Uses string IDs (UUID/ULID compatible).                      ║
 * ║  TEMPORAL ACCURACY: occurredAt is immutable, set at creation time.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 * Implements DomainEvent (canonical) via Bridge (W-01).
 * Uses HasEventIdentity (W-03).
 */
final readonly class PaymentReported implements DomainEvent, ShouldBeStoredInOutbox, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

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
        ?string $restoredEventId       = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return $this->paymentId; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }

    // ─── ShouldBeStoredInOutbox ───────────────────────────────────────────────
    public function getAggregateId(): ?string    { return $this->paymentId; }
    public function getAggregateType(): ?string  { return 'PaymentReport'; }

    public function toPrimitives(): array { return $this->toPayload(); }
    public static function fromPrimitives(array $primitives): self { return static::fromPayload($primitives); }

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * Reconstruct event from safe payload.
     * Eliminates "Metadata Bomb" and resolves Case Mismatch.
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            paymentId:              (string) ($payload['payment_id']       ?? ''),
            tenantId:               (string) ($payload['tenant_id']        ?? ''),
            amountInCents:          (int)    ($payload['amount_in_cents']  ?? 0),
            currency:               (string) ($payload['currency']         ?? 'HNL'),
            reference:              (string) ($payload['reference']        ?? ''),
            idempotencyKey:         isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
            schemaVersion:          (int)    ($payload['schema_version']   ?? self::SCHEMA_VERSION),
            restoredEventId:        $payload['event_id']       ?? null,
            restoredCorrelationId:  $payload['correlation_id'] ?? null,
        );
    }

    /**
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
