<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\LandingPageRepository;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * EloquentLandingPageRepository - Infrastructure Implementation
 */
final class EloquentLandingPageRepository implements LandingPageRepository
{
    public function getCategories(): Collection
    {
        $columns = collect(['id', 'name', 'slug', 'icon', 'color'])
            ->filter(fn (string $column): bool => Schema::hasColumn('categories', $column))
            ->values()
            ->all();

        $query = Category::query()->select($columns);

        if (Schema::hasColumn('categories', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('categories', 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        } else {
            $query->orderBy('name');
        }

        return $query->take(12)->get();
    }

    public function getPopularCities(): Collection
    {
        $columns = collect(['id', 'name', 'slug', 'hero_image', 'department'])
            ->filter(fn (string $column): bool => Schema::hasColumn('cities', $column))
            ->values()
            ->all();

        $query = City::query()
            ->select($columns);

        if (Schema::hasColumn('cities', 'is_available')) {
            $query->where('is_available', true);
        }

        if (Schema::hasColumn('tenants', 'city_id')) {
            return $query
                ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)])
                ->orderByDesc('tenants_count')
                ->take(6)
                ->get();
        }

        return $query
            ->orderBy('name')
            ->take(6)
            ->get()
            ->each(fn (City $city) => $city->setAttribute('tenants_count', 0));
    }

    public function getLatestTenants(): Collection
    {
        $columns = collect(['id', 'name', 'slug', 'logo_path', 'category_id', 'created_at', 'subscription_status', 'plan_type'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tenants', $column))
            ->values()
            ->all();

        $relations = [];

        if (Schema::hasColumn('tenants', 'category_id')) {
            $relations[] = 'category:id,name,color';
        }

        $query = Tenant::query()
            ->where('is_active', true)
            ->with($relations)
            ->select($columns);

        if (Schema::hasColumn('tenants', 'status')) {
            $query->where('status', 'approved');
        }

        if (Schema::hasColumn('tenants', 'created_at')) {
            $query->latest();
        } else {
            $query->orderBy('name');
        }

        return $query->take(8)->get();
    }

    public function getRandomHeroImage(): ?string
    {
        if (! Schema::hasColumn('cities', 'hero_image')) {
            return null;
        }

        return City::query()
            ->whereNotNull('hero_image')
            ->inRandomOrder()
            ->value('hero_image');
    }

    public function getStats(): array
    {
        $tenantQuery = Tenant::query();
        $cityQuery = City::query();
        $categoryQuery = Category::query();

        if (Schema::hasColumn('tenants', 'is_active')) {
            $tenantQuery->where('is_active', true);
        }

        if (Schema::hasColumn('cities', 'is_available')) {
            $cityQuery->where('is_available', true);
        }

        if (Schema::hasColumn('categories', 'is_active')) {
            $categoryQuery->where('is_active', true);
        }

        return [
            'tenants' => $tenantQuery->count(),
            'cities' => $cityQuery->count(),
            'categories' => $categoryQuery->count(),
        ];
    }
}
