<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Products;

use App\Domain\Products\Repositories\ProductReadRepositoryInterface;
use App\Models\Product;

/**
 * EloquentProductReadRepository - Optimized Read Model for Products
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CQRS LITE: Returns arrays/DTOs, NOT Eloquent models                    ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  - Uses SELECT with specific columns for efficiency                     ║
 * ║  - Returns lightweight arrays ready for JSON serialization              ║
 * ║  - No active record patterns, no lazy loading                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentProductReadRepository implements ProductReadRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function search(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        // Apply optional filters
        if (! empty($filters['menu_section_id'])) {
            $query->where('menu_section_id', $filters['menu_section_id']);
        }

        if (! empty($filters['is_featured'])) {
            $query->where('is_featured', true);
        }

        $total = $query->count();

        $products = $query
            ->select([
                'id',
                'name',
                'description',
                'price',
                'compare_at_price',
                'image_path',
                'image_blurhash',
                'is_featured',
                'menu_section_id',
            ])
            ->with(['menuSection:id,name,sort_order'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn ($product) => $this->transformProduct($product))
            ->toArray();

        return [
            'products' => $products,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findForDisplay(int $tenantId, int $productId): ?array
    {
        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', $productId)
            ->with(['menuSection:id,name', 'optionGroups.options'])
            ->first();

        if (! $product) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'formatted_price' => 'L. '.number_format($product->price, 2),
            'compare_at_price' => $product->compare_at_price,
            'has_discount' => $product->compare_at_price && $product->compare_at_price > $product->price,
            'image_url' => $product->image_url,
            'image_blurhash' => $product->image_blurhash,
            'is_featured' => $product->is_featured,
            'section' => $product->menuSection ? [
                'id' => $product->menuSection->id,
                'name' => $product->menuSection->name,
            ] : null,
            'option_groups' => $product->optionGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => $group->is_required,
                'options' => $group->options->map(fn ($opt) => [
                    'id' => $opt->id,
                    'name' => $opt->name,
                    'price' => $opt->price,
                ]),
            ]),
        ];
    }

    /**
     * Transform a product model to a lightweight array.
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'formatted_price' => 'L. '.number_format($product->price, 2),
            'compare_at_price' => $product->compare_at_price,
            'has_discount' => $product->compare_at_price && $product->compare_at_price > $product->price,
            'image_url' => $product->image_url,
            'image_blurhash' => $product->image_blurhash,
            'is_featured' => $product->is_featured,
            'section' => $product->menuSection ? [
                'id' => $product->menuSection->id,
                'name' => $product->menuSection->name,
            ] : null,
        ];
    }
}
