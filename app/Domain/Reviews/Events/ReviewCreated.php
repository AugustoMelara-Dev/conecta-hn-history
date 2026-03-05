<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Events;

use DateTimeImmutable;
use DateTimeInterface;
use App\Domain\Shared\Contracts\ShouldDispatchAfterCommit;

/**
 * ReviewCreated - Domain Event for Review Submission
 *
 * Implements ShouldDispatchAfterCommit for Transactional Outbox safety.
 * This event triggers async recalculation of Product aggregates.
 */
final readonly class ReviewCreated implements ShouldDispatchAfterCommit
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
    ) {}

    /**
     * Serialize to safe payload for Outbox storage.
     *
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
        ];
    }

    /**
     * Reconstruct event from safe payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            reviewId:   (int)    ($payload['review_id']  ?? 0),
            productId:  (int)    ($payload['product_id'] ?? 0),
            tenantId:   (string) ($payload['tenant_id']  ?? ''),
            rating:     (int)    ($payload['rating']     ?? 0),
            occurredAt: isset($payload['occurred_at'])
                ? new DateTimeImmutable($payload['occurred_at'])
                : new DateTimeImmutable(),
        );
    }
}
