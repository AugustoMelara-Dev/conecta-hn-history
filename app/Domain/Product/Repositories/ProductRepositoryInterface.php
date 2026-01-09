<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

/**
 * ProductRepositoryInterface - Domain Port for Product Persistence
 *
 * Defines the contract for product storage operations.
 * Serves both Products domain (CRUD) and Reviews domain (metrics).
 */
interface ProductRepositoryInterface
{
    /**
     * Find a product by ID.
     *
     * @param int $id
     * @return object|null
     */
    public function findById(int $id): ?object;

    /**
     * Create a new product.
     *
     * @param array<string, mixed> $data
     * @return object The created product entity
     */
    public function create(array $data): object;

    /**
     * Save (update) an existing product.
     *
     * @param object $product The product entity to save
     * @return void
     */
    public function save(object $product): void;

    /**
     * Delete a product.
     *
     * @param object $product The product entity to delete
     * @return void
     */
    public function delete(object $product): void;

    /**
     * Update only the rating metrics of a product.
     *
     * This specialized method prevents race conditions by updating
     * only specific columns without affecting other product data.
     *
     * @param int $id Product ID
     * @param float $averageRating Calculated average rating
     * @param int $totalReviews Total count of reviews
     * @return void
     */
    public function updateRatingMetrics(int $id, float $averageRating, int $totalReviews): void;

    /**
     * Atomically increment stock for a product.
     *
     * Used by inventory restoration after order cancellation.
     * Atomic operation prevents race conditions.
     *
     * @param int $productId Product ID
     * @param int $quantity Quantity to add to stock
     * @return void
     */
    public function incrementStock(int $productId, int $quantity): void;
}

