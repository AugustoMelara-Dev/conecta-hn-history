<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\LandingPageRepository;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * EloquentLandingPageRepository - Infrastructure Implementation
 */
final class EloquentLandingPageRepository implements LandingPageRepository
{
    public function getCategories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->select(['id', 'name', 'slug', 'icon', 'color'])
            ->take(12)
            ->get();
    }

    public function getPopularCities(): Collection
    {
        return City::query()
            ->where('is_available', true)
            ->select(['id', 'name', 'slug', 'hero_image', 'department'])
            ->withCount(['tenants' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('tenants_count')
            ->take(6)
            ->get();
    }

    public function getLatestTenants(): Collection
    {
        return Tenant::query()
            ->where('is_active', true)
            ->where('status', 'approved')
            ->with(['category:id,name,color'])
            ->select(['id', 'name', 'slug', 'logo_path', 'category_id', 'created_at', 'subscription_status', 'plan_type'])
            ->latest()
            ->take(8)
            ->get();
    }

    public function getRandomHeroImage(): ?string
    {
        return City::query()
            ->whereNotNull('hero_image')
            ->inRandomOrder()
            ->value('hero_image');
    }

    public function getStats(): array
    {
        return [
            'tenants' => Tenant::where('is_active', true)->count(),
            'cities' => City::where('is_available', true)->count(),
            'categories' => Category::where('is_active', true)->count(),
        ];
    }
}
