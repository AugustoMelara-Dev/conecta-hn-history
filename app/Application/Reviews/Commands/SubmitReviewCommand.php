<?php

declare(strict_types=1);

namespace App\Application\Reviews\Commands;

/**
 * SubmitReviewCommand - Immutable Input Contract for Review Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MINIMAL PAYLOAD: Only IDs and primitives for fast serialization.       ║
 * ║  NO AGGREGATE LOGIC: This command triggers a single atomic write.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SubmitReviewCommand
{
    public function __construct(
        /** Tenant ID for multi-tenant isolation */
        public int $tenantId,
        
        /** Author's User ID */
        public int $userId,
        
        /** Target Product ID */
        public int $productId,
        
        /** Rating (1-5 stars) */
        public int $rating,
        
        /** Review comment text */
        public ?string $comment = null,
    ) {}
}
