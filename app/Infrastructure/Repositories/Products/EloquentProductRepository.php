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
     * {@inheritDoc}
     */
    public function findById(int $id): ?\App\Domain\Product\Entities\ProductEntity
    {
        $model = Product::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    /**
     * {@inheritDoc}
     */
    public function create(array $data): \App\Domain\Product\Entities\ProductEntity
    {
        $model = Product::create($data);

        return $this->toEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    /**
     * {@inheritDoc}
     */
    public function save(\App\Domain\Product\Entities\ProductEntity $product): \App\Domain\Product\Entities\ProductEntity
    {
        // Update or Create based on basic check or ID presence
        // Since Entity is immutable, we map properties to model.

        $data = [
            'tenant_id' => $product->tenantId,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price / 100, // Convert Cents (Entity) to Float/Decimal (DB)
            'stock' => $product->stockQuantity, // Mapped stock -> stockQuantity
            'category_id' => $product->categoryId,
            'image_path' => $product->imagePath,
            'is_visible' => $product->isVisible,
        ];

        if ($product->id) {
            $model = Product::find($product->id);
            if ($model) {
                $model->update($data);
            } else {
                // Fallback or Error? Assuming create if ID provided but not found is risky, usually strict update.
                // But generally ID non-null implies existing.
                // For safety in this "Emergency" context, if not found, we create (upsert behavior) or fail.
                // Given standard Eloquent:
                $model = Product::updateOrCreate(['id' => $product->id], $data);
            }
        } else {
            $model = Product::create($data);
        }

        return $this->toEntity($model);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(\App\Domain\Product\Entities\ProductEntity $product): void
    {
        if ($product->id) {
            Product::destroy($product->id);
        }
    }

    private function toEntity(Product $model): \App\Domain\Product\Entities\ProductEntity
    {
        return new \App\Domain\Product\Entities\ProductEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            sku: $model->sku,
            name: $model->name,
            description: $model->description ?? '',
            price: (int) ($model->price * 100), // Convert Float (DB) to Cents (Entity)
            stockQuantity: $model->stock,
            categoryId: $model->category_id,
            imagePath: $model->image_path,
            isVisible: (bool) $model->is_visible,
            createdAt: $model->created_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->created_at) : null,
            updatedAt: $model->updated_at instanceof \Carbon\Carbon ? \DateTimeImmutable::createFromMutable($model->updated_at) : null,
        );
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     *
     * Atomic increment to prevent race conditions.
     */
    public function incrementStock(int $productId, int $quantity): void
    {
        Product::where('id', $productId)->increment('stock', $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function existsBySkuExcludingId(string $sku, int|string $productId): bool
    {
        return Product::where('sku', $sku)
            ->where('id', '!=', $productId)
            ->exists();
    }
}
