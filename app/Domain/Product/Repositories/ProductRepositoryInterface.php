<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

use App\Domain\Product\Entities\ProductEntity;

/**
 * ProductRepositoryInterface - Domain Port for Product Persistence
 *
 * Defines the contract for product storage operations.
 * Serves both Products domain (CRUD) and Reviews domain (metrics).
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CONTRACT HARDENING:                                                    ║
 * ║  - Strict Typing: Inputs and Outputs must be ProductEntity.             ║
 * ║  - No 'object' or 'mixed' allowed.                                      ║
 * ║  - Enforces Re-hydration pattern (save returns fresh instance).         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface ProductRepositoryInterface
{
    public function findById(int $id): ?ProductEntity;

    /**
     * @param  array<string, mixed>  $data
     *
     * @deprecated Use Factory + save() instead for strictly typed creation.
     */
    public function create(array $data): ProductEntity;

    public function save(ProductEntity $product): ProductEntity;

    public function delete(ProductEntity $product): void;

    // Specialized Metric Updates (kept as void since they don't hydrate full entity usually)
    public function updateRatingMetrics(int $id, float $averageRating, int $totalReviews): void;

    // Atomic Stock Update
    public function incrementStock(int $productId, int $quantity): void;

    // Uniqueness Check for SKU validation (excludes given product ID to allow self-update)
    public function existsBySkuExcludingId(string $sku, int|string $productId): bool;
}
