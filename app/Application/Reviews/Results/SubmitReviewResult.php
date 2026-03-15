<?php

declare(strict_types=1);

namespace App\Application\Reviews\Results;

use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * SubmitReviewResult - Hybrid DTO for public review intake and review result exposure.
 */
final readonly class SubmitReviewResult
{
    public function __construct(
        public string $tenantId,
        public ?int $productId,
        public int $rating,
        public ?string $comment,
        public ?int $id = null,
        public ?string $status = null,
        public ?bool $isPublished = null,
        public ?DateTimeImmutable $submittedAt = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            tenantId: (string) $request->input('tenant_id'),
            productId: $request->filled('product_id') ? (int) $request->input('product_id') : null,
            rating: (int) $request->input('rating'),
            comment: $request->input('comment')
        );
    }

    public static function fromEntity(object $review): self
    {
        return new self(
            tenantId: (string) ($review->tenant_id ?? ''),
            productId: isset($review->product_id) ? (int) $review->product_id : null,
            rating: (int) $review->rating,
            comment: $review->comment ?? null,
            id: (int) $review->id,
            status: ($review->is_approved ?? false) ? 'APPROVED' : 'PENDING_MODERATION',
            isPublished: (bool) ($review->is_approved ?? false),
            submittedAt: $review->created_at instanceof DateTimeImmutable
                ? $review->created_at
                : new DateTimeImmutable((string) $review->created_at),
        );
    }
}