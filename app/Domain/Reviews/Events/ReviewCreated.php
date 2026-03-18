<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Events;

use App\Domain\Shared\Contracts\Events\DomainEvent;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;
use App\Domain\Shared\Traits\HasEventIdentity;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * ReviewCreated - Domain Event for Review Submission
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * This event triggers async recalculation of Product aggregates.
 * Implements DomainEvent (canonical) via Bridge pattern (W-01).
 * Uses HasEventIdentity (W-03) for auto-generated eventId/correlationId.
 */
final readonly class ReviewCreated implements DomainEvent, ShouldDispatchAfterCommit
{
    use HasEventIdentity;

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
        return (string) $this->reviewId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
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
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'review_id' => $this->reviewId,
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'rating' => $this->rating,
            'occurred_at' => $this->occurredAt->format(DateTimeInterface::ATOM),
            'event_id' => $this->eventId,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static
    {
        return new self(
            reviewId: (int) ($payload['review_id'] ?? 0),
            productId: (int) ($payload['product_id'] ?? 0),
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            rating: (int) ($payload['rating'] ?? 0),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable,
            restoredEventId: $payload['event_id'] ?? null,
            restoredCorrelationId: $payload['correlation_id'] ?? null,
        );
    }
}
