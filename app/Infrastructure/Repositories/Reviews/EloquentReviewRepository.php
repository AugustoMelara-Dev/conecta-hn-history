<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Reviews;

use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;
use App\Models\Review;

/**
 * EloquentReviewRepository - Infrastructure Adapter for Review Persistence
 *
 * Implements the ReviewRepositoryInterface using Eloquent ORM.
 * Optimized SQL aggregation for performance.
 */
final class EloquentReviewRepository implements ReviewRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create(array $data): object
    {
        // Ownership fields are guarded on the model and originate from the
        // validated command boundary, so the persistence adapter sets them explicitly.
        return Review::query()->forceCreate($data);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?object
    {
        return Review::find($id);
    }

    /**
     * {@inheritDoc}
     *
     * Uses SQL aggregate functions for efficient calculation.
     * NEVER loads all reviews into PHP memory.
     */
    public function getAggregateMetrics(int $productId): array
    {
        $result = Review::where('product_id', $productId)
            ->where('is_approved', true) // Only approved reviews count
            ->selectRaw('COALESCE(AVG(rating), 0) as average, COUNT(*) as total')
            ->first();

        return [
            'average_rating' => (float) ($result->average ?? 0),
            'total_reviews' => (int) ($result->total ?? 0),
        ];
    }
}
