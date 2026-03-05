<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;

/**
 * ProductCreated - Domain Event for Product Creation
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * Implements DomainEvent (canonical) via Bridge pattern (W-01).
 * Uses HasEventIdentity (W-03) for auto-generated eventId/correlationId.
 */
final readonly class ProductCreated implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $productId,
        public string $tenantId,
        public string $name,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
        ?string $restoredEventId       = null,
        ?string $restoredCorrelationId = null,
    ) {
        $this->initEventIdentity($restoredEventId, $restoredCorrelationId);
    }

    // ─── DomainEvent interface ────────────────────────────────────────────────
    // eventId() and correlationId() provided by HasEventIdentity trait.

    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return (string) $this->productId; }
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
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'product_id'     => $this->productId,
            'tenant_id'      => $this->tenantId,
            'name'           => $this->name,
            'occurred_at'    => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id'       => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            productId:              (int)    ($payload['product_id']     ?? 0),
            tenantId:               (string) ($payload['tenant_id']      ?? ''),
            name:                   (string) ($payload['name']           ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
            restoredEventId:        $payload['event_id']       ?? null,
            restoredCorrelationId:  $payload['correlation_id'] ?? null,
        );
    }
}
