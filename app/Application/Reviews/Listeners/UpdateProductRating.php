<?php

declare(strict_types=1);

namespace App\Application\Reviews\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Product\Repositories\ProductRatingMetricsWriter;
use App\Domain\Reviews\Events\ReviewCreated;
use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * UpdateProductRating - Async Listener for Product Aggregate Recalculation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EVENTUAL CONSISTENCY: Runs async after ReviewCreated event.            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Heavy SQL aggregation moved here (no blocking on user request)       ║
 * ║  - Tenant-aware via TenantQueueMiddleware                               ║
 * ║  - Retryable: If it fails, the queue will retry automatically          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateProductRating implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly ProductRatingMetricsWriter $ratingMetrics,
        private readonly ReviewRepositoryInterface $reviewRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ReviewCreated $event): void
    {
        // 1. Fetch Aggregate Metrics (Heavy SQL Query)
        $metrics = $this->reviewRepository->getAggregateMetrics($event->productId);

        // The narrow port owns only derived review metrics, never catalog CRUD.
        if (! $this->ratingMetrics->updateRatingMetrics(
            $event->tenantId,
            $event->productId,
            $metrics['average_rating'] ?? 0,
            $metrics['total_reviews'] ?? 0,
        )) {
            $this->logger->warning('UpdateProductRating: Product not found', [
                'product_id' => $event->productId,
                'review_id' => $event->reviewId,
            ]);

            return;
        }

        $this->logger->info('Product rating updated via eventual consistency', [
            'product_id' => $event->productId,
            'new_average' => $metrics['average_rating'] ?? 0,
            'total_reviews' => $metrics['total_reviews'] ?? 0,
        ]);
    }

    /**
     * Middleware for tenant-aware queue processing.
     *
     * SECURITY CRITICAL: Ensures the listener runs in the correct tenant context.
     * Without this, database writes could target the wrong tenant.
     *
     * @return array<int, object>
     */
    public function middleware(ReviewCreated $event): array
    {
        return [
            new TenantQueueMiddleware($event->tenantId),
        ];
    }
}
