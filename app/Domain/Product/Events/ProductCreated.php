<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ProductCreated - Domain Event for Product Creation
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * Implements DomainEvent (canonical) via Bridge pattern:
 *   toPrimitives() delegates to toPayload()
 *   fromPrimitives() delegates to fromPayload()
 */
final readonly class ProductCreated implements DomainEvent, ShouldDispatchAfterCommit
{
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
        /** W-01: DomainEvent metadata — populated by Outbox relay or Handler */
        public string $eventId = '',
        public string $correlationId = '',
    ) {}

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function eventId(): string        { return $this->eventId; }
    public function correlationId(): string  { return $this->correlationId; }
    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return (string) $this->productId; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }

    /** Bridge: delegates to toPayload() — no consumers broken. */
    public function toPrimitives(): array
    {
        return $this->toPayload();
    }

    /** Bridge: delegates to fromPayload() — no consumers broken. */
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
            productId:      (int)    ($payload['product_id']     ?? 0),
            tenantId:       (string) ($payload['tenant_id']      ?? ''),
            name:           (string) ($payload['name']           ?? ''),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
            eventId:        (string) ($payload['event_id']       ?? ''),
            correlationId:  (string) ($payload['correlation_id'] ?? ''),
        );
    }
}
