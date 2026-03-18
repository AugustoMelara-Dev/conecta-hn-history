<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Domain\Directory\Contracts\DirectoryReader;
use App\Domain\Directory\DTOs\DirectorySearchData;
use App\Domain\Shared\Contracts\PaginatedResult;
use App\Infrastructure\Shared\EloquentPaginatedResult;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

final class EloquentDirectoryReader implements DirectoryReader
{
    private const CACHE_TTL_MINUTES = 10;

    private const LONG_CACHE_TTL_HOURS = 1;

    public function getFeaturedTenants(int $limit = 8): array
    {
        return Cache::remember("directory_featured_{$limit}", now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($limit) {
            return Tenant::query()
                ->select(['id', 'name', 'slug', 'description', 'tags', 'is_active', 'hero_image', 'banner_path', 'cover_path', 'blur_hash', 'brand_color', 'plan_type', 'subscription_status', 'city_id', 'category_id'])
                ->published()
                ->inPriorityOrder()
                ->take($limit)
                ->get()
                ->toArray();
        });
    }

    public function searchTenants(DirectorySearchData $filters): PaginatedResult
    {
        $cacheKey = sprintf(
            'directory_page_%d_cat_%s_city_%s_search_%s',
            $filters->page,
            $filters->categorySlug ?? 'all',
            $filters->citySlug ?? 'all',
            md5($filters->search ?? '')
        );

        $paginator = Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($filters) {
                return Tenant::query()
                    ->select(['id', 'name', 'slug', 'description', 'tags', 'is_active', 'hero_image', 'banner_path', 'cover_path', 'blur_hash', 'brand_color', 'plan_type', 'subscription_status', 'subscription_ends_at', 'city_id', 'category_id', 'created_at'])
                    ->published()
                    ->search($filters->search)
                    ->inCategory($filters->categorySlug)
                    ->inCity($filters->citySlug)
                    ->inPriorityOrder()
                    ->latest('created_at')
                    ->with(['city:id,name,slug', 'category:id,name,slug,icon'])
                    ->paginate($filters->perPage, ['*'], 'page', $filters->page);
            }
        );

        return new EloquentPaginatedResult($paginator);
    }

    public function getCitiesWithTenants(): array
    {
        return Cache::remember('v'.config('app.version').'_directory_cities', now()->addHours(self::LONG_CACHE_TTL_HOURS), function () {
            return City::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    public function getCategoriesWithTenants(): array
    {
        return Cache::remember('v'.config('app.version').'_directory_categories', now()->addHours(self::LONG_CACHE_TTL_HOURS), function () {
            return Category::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'slug', 'icon'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'icon' => $cat->icon ?? '🏪',
                ])
                ->toArray();
        });
    }

    public function findCityBySlug(string $slug): ?object
    {
        return City::query()
            ->select(['id', 'name', 'slug'])
            ->where('slug', $slug)
            ->first();
    }

    public function findCategoryBySlug(string $slug): ?object
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'icon'])
            ->where('slug', $slug)
            ->first();
    }
}
