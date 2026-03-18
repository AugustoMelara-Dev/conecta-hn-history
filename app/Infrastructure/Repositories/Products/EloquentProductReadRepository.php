<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Products;

use App\Domain\Product\Repositories\ProductReadRepositoryInterface;
use App\Models\MenuSection;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class EloquentProductReadRepository implements ProductReadRepositoryInterface
{
    public function search(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

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
                'image_path',
                'blur_hash',
                'is_featured',
                'menu_section_id',
            ])
            ->with(['menuSection:id,name,sort_order'])
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (Product $product): array => $this->transformProduct($product))
            ->values()
            ->all();

        return [
            'products' => $products,
            'total' => $total,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    public function findForDisplay(int $tenantId, int $productId): ?array
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('id', $productId)
            ->with(['menuSection:id,name', 'optionGroups.options'])
            ->first();

        if ($product === null) {
            return null;
        }

        /** @var MenuSection|null $menuSection */
        $menuSection = $product->menuSection;
        /** @var EloquentCollection<int, ProductOptionGroup> $optionGroups */
        $optionGroups = $product->optionGroups;

        $price = (float) $product->price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $price,
            'formatted_price' => 'L. '.number_format($price, 2),
            'compare_at_price' => null,
            'has_discount' => false,
            'image_url' => $product->image_url,
            'image_blurhash' => $product->getAttribute('blur_hash'),
            'is_featured' => (bool) $product->is_featured,
            'section' => $menuSection ? [
                'id' => $menuSection->id,
                'name' => $menuSection->name,
            ] : null,
            'option_groups' => $this->transformOptionGroups($optionGroups),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product): array
    {
        /** @var MenuSection|null $menuSection */
        $menuSection = $product->menuSection;

        $price = (float) $product->price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $price,
            'formatted_price' => 'L. '.number_format($price, 2),
            'compare_at_price' => null,
            'has_discount' => false,
            'image_url' => $product->image_url,
            'image_blurhash' => $product->getAttribute('blur_hash'),
            'is_featured' => (bool) $product->is_featured,
            'section' => $menuSection ? [
                'id' => $menuSection->id,
                'name' => $menuSection->name,
            ] : null,
        ];
    }

    /**
     * @param  EloquentCollection<int, ProductOptionGroup>  $optionGroups
     * @return array<int, array<string, mixed>>
     */
    private function transformOptionGroups(EloquentCollection $optionGroups): array
    {
        $result = [];

        foreach ($optionGroups as $group) {
            /** @var EloquentCollection<int, ProductOption> $options */
            $options = $group->options;

            $result[] = [
                'id' => (int) $group->getKey(),
                'name' => (string) $group->getAttribute('name'),
                'is_required' => (bool) $group->getAttribute('is_required'),
                'options' => $this->transformOptions($options),
            ];
        }

        return $result;
    }

    /**
     * @param  EloquentCollection<int, ProductOption>  $options
     * @return array<int, array{id:int, name:string, price:float}>
     */
    private function transformOptions(EloquentCollection $options): array
    {
        $result = [];

        foreach ($options as $option) {
            $result[] = [
                'id' => (int) $option->getKey(),
                'name' => (string) $option->getAttribute('name'),
                'price' => (float) $option->getAttribute('price_modifier'),
            ];
        }

        return $result;
    }
}
