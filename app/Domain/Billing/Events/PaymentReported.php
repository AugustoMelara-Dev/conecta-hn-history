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
 * Domain event emitted when a tenant reports a manual payment.
 *
 * The schema version is part of the event contract so future upcasters can
 * evolve the payload without breaking existing consumers.
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
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
        public int $schemaVersion = self::SCHEMA_VERSION,
        ?string $restoredEventId = null,
        ?string $restoredCorrelationId = null,
    ) {
        $identity = self::resolveEventIdentity($restoredEventId, $restoredCorrelationId);
        $this->eventId = $identity['eventId'];
        $this->correlationId = $identity['correlationId'];
    }

    public function tenantId(): mixed
    {
        return $this->tenantId;
    }

    public function aggregateId(): string
    {
        return $this->paymentId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getAggregateId(): string
    {
        return $this->paymentId;
    }

    public function getAggregateType(): string
    {
        return 'PaymentReport';
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }

    /**
     * Reconstruct the event from a safe payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            paymentId: (string) ($payload['payment_id'] ?? ''),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            amountInCents: (int) ($payload['amount_in_cents'] ?? 0),
            currency: (string) ($payload['currency'] ?? 'HNL'),
            reference: (string) ($payload['reference'] ?? ''),
            idempotencyKey: isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'] : null,
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable,
            schemaVersion: (int) ($payload['schema_version'] ?? self::SCHEMA_VERSION),
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'payment_id' => $this->paymentId,
            'tenant_id' => $this->tenantId,
            'amount_in_cents' => $this->amountInCents,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
