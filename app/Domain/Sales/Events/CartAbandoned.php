<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldBeStoredInOutbox;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Traits\HasDomainMetadata;

/**
 * CartAbandoned - Domain Event with Transactional Outbox Support
 *
 * Fired when a checkout session remains inactive beyond the configured threshold.
 */
final readonly class CartAbandoned implements DomainEvent, ShouldBeStoredInOutbox
{
    use HasDomainMetadata;

    public const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $checkoutSessionId,
        public string $tenantId,
        public string $sessionId,
        public string|int|null $userId,
        public float $subtotal,
        ?string $restoredEventId = null,
        ?string $restoredOccurredAt = null,
        ?string $restoredCorrelationId = null,
    ) {
        $metadata = self::resolveMetadata(
            correlationId: $restoredCorrelationId,
            restoredEventId: $restoredEventId,
            restoredOccurredAt: $restoredOccurredAt,
        );
        $this->eventId = $metadata['eventId'];
        $this->correlationId = $metadata['correlationId'];
        $this->occurredAt = $metadata['occurredAt'];
    }

    public function tenantId(): TenantId
    {
        return TenantId::fromString($this->tenantId);
    }

    public function aggregateId(): string
    {
        return (string) $this->checkoutSessionId;
    }

    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'checkout_session_id' => $this->checkoutSessionId,
            'tenant_id' => $this->tenantId,
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'subtotal' => $this->subtotal,
            'event_id' => $this->eventId,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'correlation_id' => $this->correlationId,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            checkoutSessionId: (int) $payload['checkout_session_id'],
            tenantId: (string) $payload['tenant_id'],
            sessionId: (string) $payload['session_id'],
            userId: $payload['user_id'] ?? null,
            subtotal: (float) $payload['subtotal'],
            restoredEventId: $payload['event_id'] ?? null,
            restoredOccurredAt: $payload['occurred_at'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }

    public function getAggregateId(): string
    {
        return (string) $this->checkoutSessionId;
    }

    public function getAggregateType(): string
    {
        return 'checkout_session';
    }

    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    public static function fromPrimitives(array $primitives): self
    {
        return self::fromPayload($primitives);
    }
}
