<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ReviewCreated - Domain Event for Review Submission
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * This event triggers async recalculation of Product aggregates.
 * Implements DomainEvent (canonical) via Bridge pattern.
 */
final readonly class ReviewCreated implements DomainEvent, ShouldDispatchAfterCommit
{
    private const SCHEMA_VERSION = 1;

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function __construct(
        public int $reviewId,
        public int $productId,
        public string $tenantId,
        public int $rating,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable(),
        /** W-01: DomainEvent metadata — populated by Outbox relay or Handler */
        public string $eventId = '',
        public string $correlationId = '',
    ) {}

    // ─── DomainEvent interface ────────────────────────────────────────────────

    public function eventId(): string        { return $this->eventId; }
    public function correlationId(): string  { return $this->correlationId; }
    public function tenantId(): mixed        { return $this->tenantId; }
    public function aggregateId(): string    { return (string) $this->reviewId; }
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
            'review_id'      => $this->reviewId,
            'product_id'     => $this->productId,
            'tenant_id'      => $this->tenantId,
            'rating'         => $this->rating,
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
            reviewId:       (int)    ($payload['review_id']      ?? 0),
            productId:      (int)    ($payload['product_id']     ?? 0),
            tenantId:       (string) ($payload['tenant_id']      ?? ''),
            rating:         (int)    ($payload['rating']         ?? 0),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
            eventId:        (string) ($payload['event_id']       ?? ''),
            correlationId:  (string) ($payload['correlation_id'] ?? ''),
        );
    }
}
