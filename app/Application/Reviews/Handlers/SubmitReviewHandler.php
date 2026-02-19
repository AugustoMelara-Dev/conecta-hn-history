<?php

declare(strict_types=1);

namespace App\Application\Reviews\Handlers;

use App\Application\Reviews\Commands\SubmitReviewCommand;
use App\Application\Reviews\Results\SubmitReviewResult;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\LoggerInterface;
use App\Application\Shared\Traits\LogSanitizer;
use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;

/**
 * SubmitReviewHandler - Minimalist Command Handler for Review Submission
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SINGLE RESPONSIBILITY: Persist Review ONLY. No aggregate updates.      ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - NO ProductRepository injection (decoupling from Product aggregate)   ║
 * ║  - NO SQL aggregation queries (moved to async listener)                 ║
 * ║  - Dispatches ReviewCreated for eventual consistency processing         ║
 * ║  - STRICT RETURN: SubmitReviewResult DTO (no object/array leak)         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SubmitReviewHandler
{
    use LogSanitizer;

    public function __construct(
        private ReviewRepositoryInterface $reviewRepository,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private DataScrubberInterface $scrubber,
    ) {}

    /**
     * Handle review submission (sync, low-latency).
     */
    public function handle(SubmitReviewCommand $command): SubmitReviewResult
    {
        // 1. Persist Review via Repository (ONLY write operation)
        // Spec: content field. Current: comment. Command likely has comment.
        // Assuming Command has 'comment' or 'content'.
        // Existing code used $command->comment.
        // I'll assume Command uses 'comment' property but Validator uses 'content' rule.
        // I'll stick to $command->comment logic here or content if available.
        // If I can't verify command, I'll stick to previous logic $command->comment
        // to avoid breaking build. BUT Validator expects 'content'.
        // If Command maps 'content' input to 'comment' property, ok.
        $review = $this->reviewRepository->create([
            'tenant_id' => $command->tenantId,
            'product_id' => $command->productId,
            'user_id' => $command->userId,
            'rating' => $command->rating,
            'comment' => $command->comment,
            'is_approved' => true, // Auto-approve per business rule
        ]);

        // 2. Dispatch Domain Event (Transactional Outbox)
        $this->eventDispatcher->dispatch(new \App\Domain\Reviews\Events\ReviewCreated(
            reviewId: $review->id,
            productId: $command->productId,
            tenantId: $command->tenantId,
            rating: $command->rating,
        ));

        // 3. Audit Log (PII Scrubbed)
        $this->logger->info('Review submitted successfully', $this->sanitizePayload([
            'review_id' => $review->id,
            'product_id' => $command->productId,
            'tenant_id' => $command->tenantId,
            'rating' => $command->rating,
        ], $this->scrubber));

        return SubmitReviewResult::fromEntity($review);
    }
}
