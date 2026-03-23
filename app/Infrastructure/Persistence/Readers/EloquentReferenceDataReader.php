<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Readers;

use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Models\Category;
use App\Models\City;

/**
 * EloquentReferenceDataReader - Infrastructure Adapter for Reference Data
 *
 * ONLY CLASS allowed to import City and Category models.
 */
final class EloquentReferenceDataReader implements ReferenceDataReaderInterface
{
    public function getAvailableCities(): array
    {
        return City::query()
            ->where('is_available', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getActiveCategories(): array
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getPopularCategories(int $limit = 8): array
    {
        return Category::query()
            ->active()
            ->ordered()
            ->take($limit)
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $category->icon,
            ])
            ->all();
    }

    public function getPopularCities(int $limit = 4): array
    {
        return City::query()
            ->where('is_available', true)
            ->select(['id', 'name', 'slug', 'hero_image', 'department'])
            ->withCount(['tenants' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('tenants_count')
            ->take($limit)
            ->get()
            ->map(fn (City $city) => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'hero_image' => $city->hero_image,
                'department' => $city->department,
                'tenants_count' => (int) $city->tenants_count,
            ])
            ->all();
    }

    public function countActiveCities(): int
    {
        return City::query()
            ->where('is_available', true)
            ->count();
    }
}
