<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Repositories;

/**
 * ReviewRepositoryInterface - Domain Port for Review Persistence
 *
 * Defines the contract for review storage operations.
 * Infrastructure implementations (Eloquent, Doctrine) live elsewhere.
 */
interface ReviewRepositoryInterface
{
    /**
     * Create a new review.
     *
     * @param  array<string, mixed>  $data
     * @return object The created review entity
     */
    public function create(array $data): object;

    /**
     * Find a review by ID.
     */
    public function findById(int $id): ?object;

    /**
     * Get aggregate metrics for a product's reviews.
     *
     * @return array{average_rating: float, total_reviews: int}
     */
    public function getAggregateMetrics(int $productId): array;
}
