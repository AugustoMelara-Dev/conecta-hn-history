<?php

declare(strict_types=1);

namespace App\Application\Reviews\Listeners;

use App\Application\Shared\Contracts\LoggerInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Reviews\Events\ReviewCreated;
use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;

final readonly class UpdateProductRating
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ReviewRepositoryInterface $reviewRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ReviewCreated $event): void
    {
        // 1. Fetch Aggregate Metrics (Heavy SQL Query)
        $metrics = $this->reviewRepository->getAggregateMetrics($event->productId);

        // 2. Load Product Aggregate Root
        $product = $this->productRepository->findById($event->productId);

        if ($product === null) {
            $this->logger->warning('UpdateProductRating: Product not found', [
                'product_id' => $event->productId,
                'review_id' => $event->reviewId,
            ]);

            return;
        }

        // 3. Update Product with New Metrics
        $this->productRepository->updateRatingMetrics(
            $event->productId,
            $metrics['average_rating'],
            $metrics['total_reviews']
        );

        $this->logger->info('Product rating updated via eventual consistency', [
            'product_id' => $event->productId,
            'new_average' => $metrics['average_rating'],
            'total_reviews' => $metrics['total_reviews'],
        ]);
    }
}
