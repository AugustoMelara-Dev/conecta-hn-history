<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Events;

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
    public function schemaVersion(): int { return self::SCHEMA_VERSION; }
    public function __construct(
        public int $reviewId,
        public int $productId,
        public string $tenantId,
        public int $rating,
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
            'review_id' => $this->reviewId,
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'rating' => $this->rating,
            'occurred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }
}
