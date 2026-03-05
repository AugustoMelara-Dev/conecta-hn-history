<?php

declare(strict_types=1);

namespace App\Application\Reviews\Results;

use DateTimeImmutable;

/**
 * SubmitReviewResult - Immutable Output Contract for Review Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SEMANTIC CONTRACT: Exposes review status without Eloquent leakage.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SubmitReviewResult
{
    public function __construct(
        public int $id,
        public string $status,
        public float $rating,
        public bool $isPublished,
        public DateTimeImmutable $submittedAt,
    ) {}

    /**
     * Factory method to hydrate from Domain Entity / Eloquent Model.
     */
    public static function fromEntity(object $review): self
    {
        return new self(
            id: $review->id,
            status: $review->is_approved ? 'APPROVED' : 'PENDING_MODERATION',
            rating: (float) $review->rating,
            isPublished: (bool) $review->is_approved,
            submittedAt: $review->created_at instanceof DateTimeImmutable
                ? $review->created_at
                : new DateTimeImmutable((string) $review->created_at),
        );
    }
}
