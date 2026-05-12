<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Domain\Directory\Contracts\DirectoryReader;
use App\Domain\Directory\DTOs\DirectorySearchData;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator as LaravelLengthAwarePaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EloquentDirectoryReader implements DirectoryReader
{
    private const CACHE_TTL_MINUTES = 10;

    private const LONG_CACHE_TTL_HOURS = 1;

    public function getFeaturedTenants(int $limit = 8): Collection
    {
        return Cache::remember("directory_featured_{$limit}", now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($limit) {
            if (! Schema::hasTable('tenants')) {
                return collect();
            }

            return Tenant::query()
                ->select($this->tenantColumns([
                    'id', 'name', 'slug', 'description', 'tags', 'is_active',
                    'hero_image', 'banner_path', 'cover_path', 'blur_hash',
                    'brand_color', 'plan_type', 'subscription_status', 'city_id', 'category_id',
                ]))
                ->tap(fn ($query) => $this->applyPublished($query))
                ->tap(fn ($query) => $this->applyPriorityOrder($query))
                ->take($limit)
                ->get();
        });
    }

    public function searchTenants(DirectorySearchData $filters): LengthAwarePaginator
    {
        // Deterministic cache key (includes search hash for uniqueness)
        $cacheKey = sprintf(
            'directory_page_%d_cat_%s_city_%s_search_%s',
            $filters->page,
            $filters->categorySlug ?? 'all',
            $filters->citySlug ?? 'all',
            md5($filters->search ?? '')
        );

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($filters) {
                if (! Schema::hasTable('tenants')) {
                    return new LaravelLengthAwarePaginator(
                        collect(),
                        0,
                        $filters->perPage,
                        $filters->page,
                        ['path' => request()->url(), 'pageName' => 'page']
                    );
                }

                return Tenant::query()
                    // PROJECTION: Only fields needed for card rendering
                    ->select($this->tenantColumns([
                        'id', 'name', 'slug', 'description', 'tags', 'is_active',
                        'hero_image', 'banner_path', 'cover_path', 'blur_hash',
                        'brand_color', 'plan_type', 'subscription_status', 'subscription_ends_at',
                        'city_id', 'category_id', 'created_at',
                    ]))
                    ->tap(fn ($query) => $this->applyPublished($query))
                    ->tap(fn ($query) => $this->applySearch($query, $filters->search))
                    ->tap(fn ($query) => $this->applyCategory($query, $filters->categorySlug))
                    ->tap(fn ($query) => $this->applyCity($query, $filters->citySlug))
                    ->tap(fn ($query) => $this->applyPriorityOrder($query))
                    ->tap(fn ($query) => $this->applyLatest($query))
                    ->with($this->tenantRelations())
                    ->paginate($filters->perPage, ['*'], 'page', $filters->page);
            }
        );
    }

    public function getCitiesWithTenants(): Collection
    {
        return Cache::remember("v".config('app.version')."_directory_cities", now()->addHours(self::LONG_CACHE_TTL_HOURS), function () {
            if (! Schema::hasTable('cities')) {
                return collect();
            }

            return City::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get();
        });
    }

    public function getCategoriesWithTenants(): Collection
    {
        return Cache::remember("v".config('app.version')."_directory_categories", now()->addHours(self::LONG_CACHE_TTL_HOURS), function () {
            if (! Schema::hasTable('categories')) {
                return collect();
            }

            return Category::query()
                ->when(Schema::hasColumn('categories', 'is_active'), fn ($query) => $query->where('is_active', true))
                ->select($this->categoryColumns(['id', 'name', 'slug', 'icon']) ?: ['*'])
                ->orderBy('name')
                ->get()
                ->map(function (Category $cat): array {
                    $attributes = $cat->getAttributes();

                    return [
                        'id' => $attributes['id'] ?? $cat->id,
                        'name' => $attributes['name'] ?? $cat->name,
                        'slug' => $attributes['slug'] ?? $cat->slug,
                        'icon' => $attributes['icon'] ?? 'store',
                    ];
                });
        });
    }

    public function findCityBySlug(string $slug): ?object
    {
        if (! Schema::hasTable('cities')) {
            return null;
        }

        return City::query()
            ->select(['id', 'name', 'slug'])
            ->where('slug', $slug)
            ->first();
    }

    public function findCategoryBySlug(string $slug): ?object
    {
        if (! Schema::hasTable('categories')) {
            return null;
        }

        return Category::query()
            ->select($this->categoryColumns(['id', 'name', 'slug', 'icon']) ?: ['*'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function categoryColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('categories', $column))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function tenantColumns(array $columns): array
    {
        return collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))
            ->values()
            ->all();
    }

    private function tenantRelations(): array
    {
        $relations = [];

        if (Schema::hasTable('cities') && Schema::hasColumn('tenants', 'city_id')) {
            $relations[] = 'city:id,name,slug';
        }

        if (Schema::hasTable('categories') && Schema::hasColumn('tenants', 'category_id')) {
            $categoryColumns = $this->categoryColumns(['id', 'name', 'slug', 'icon']);

            $relations[] = $categoryColumns !== []
                ? 'category:'.implode(',', $categoryColumns)
                : 'category';
        }

        return $relations;
    }

    private function applyPublished($query): void
    {
        if (Schema::hasColumn('tenants', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $query->where('subscription_status', 'active');
        }
    }

    private function applySearch($query, ?string $search): void
    {
        if (blank($search)) {
            return;
        }

        $columns = collect(['name', 'description', 'tags'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))
            ->values();

        if ($columns->isEmpty()) {
            return;
        }

        $term = mb_substr(trim($search), 0, 100);

        $query->where(function ($inner) use ($columns, $term) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $inner->{$method}($column, 'like', "%{$term}%");
            }
        });
    }

    private function applyCategory($query, ?string $categorySlug): void
    {
        if ($categorySlug && Schema::hasTable('categories') && Schema::hasColumn('tenants', 'category_id')) {
            $query->inCategory($categorySlug);
        }
    }

    private function applyCity($query, ?string $citySlug): void
    {
        if ($citySlug && Schema::hasTable('cities') && Schema::hasColumn('tenants', 'city_id')) {
            $query->inCity($citySlug);
        }
    }

    private function applyPriorityOrder($query): void
    {
        if (Schema::hasColumn('tenants', 'plan_type')) {
            if (DB::connection()->getDriverName() === 'mysql') {
                $query->orderByRaw("FIELD(plan_type, 'enterprise', 'pro', 'semilla')");

                return;
            }

            $query->orderByRaw("case plan_type when 'enterprise' then 0 when 'pro' then 1 when 'semilla' then 2 else 3 end");
        }
    }

    private function applyLatest($query): void
    {
        if (Schema::hasColumn('tenants', 'created_at')) {
            $query->latest('created_at');

            return;
        }

        $query->orderBy('name');
    }
}
