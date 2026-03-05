<?php

declare(strict_types=1);

namespace App\Domain\Billing\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;

/**
 * PaymentFailed - Domain Event for Failed Payment
 *
 * Agnostic event that abstracts Stripe/PayPal into internal domain language.
 * Implements DomainEvent (canonical) via Bridge pattern (W-01).
 * Uses HasEventIdentity (W-03) for auto-generated eventId/correlationId.
 */
final readonly class PaymentFailed implements DomainEvent, ShouldDispatchAfterCommit
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
        public string $reason,
        public string $tenantId,
        public ?string $failureCode = null,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        ?string $restoredEventId       = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return $this->transactionId; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt; }

    public function toPrimitives(): array { return $this->toPayload(); }
    public static function fromPrimitives(array $primitives): self { return static::fromPayload($primitives); }

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            transactionId:          (string) ($payload['transaction_id']    ?? ''),
            providerReference:      (string) ($payload['provider_reference'] ?? ''),
            reason:                 (string) ($payload['reason']             ?? ''),
            tenantId:               (string) ($payload['tenant_id']          ?? ''),
            failureCode:            isset($payload['failure_code']) ? (string) $payload['failure_code'] : null,
            occurredAt:             new \DateTimeImmutable($payload['occurred_at'] ?? 'now'),
            restoredEventId:        $payload['event_id']       ?? null,
            restoredCorrelationId:  $payload['correlation_id'] ?? null,
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
