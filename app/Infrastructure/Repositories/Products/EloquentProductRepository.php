<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Products;

use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Models\Product;

/**
 * EloquentProductRepository - Infrastructure Adapter for Product Persistence
 *
 * Implements the ProductRepositoryInterface for both:
 * - Products domain (full CRUD operations)
 * - Reviews domain (rating metric updates)
 */
final class EloquentProductRepository implements ProductRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findById(int $id): ?object
    {
        return Product::find($id);
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): object
    {
        return Product::create($data);
    }

    /**
     * @inheritDoc
     */
    public function save(object $product): void
    {
        $product->save();
    }

    /**
     * @inheritDoc
     */
    public function delete(object $product): void
    {
        $product->delete();
    }

    /**
     * @inheritDoc
     *
     * Direct column update to prevent race conditions.
     * Does NOT load the model into memory.
     */
    public function updateRatingMetrics(int $id, float $averageRating, int $totalReviews): void
    {
        Product::where('id', $id)->update([
            'rating_avg' => $averageRating,
            'reviews_count' => $totalReviews,
        ]);
    }

    /**
     * @inheritDoc
     *
     * Atomic increment to prevent race conditions.
     */
    public function incrementStock(int $productId, int $quantity): void
    {
        Product::where('id', $productId)->increment('stock', $quantity);
    }
}

