<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant;

use App\Domain\Tenant\Contracts\OnboardingReader;
use App\Models\Category;
use App\Models\City;
use Illuminate\Support\Facades\Cache;

final class CachedOnboardingReader implements OnboardingReader
{
    private const TTL = 86400; // 24 hours

    /**
     * Get available cities with caching strategy.
     */
    public function getAvailableCities(): array
    {
        return Cache::remember(
            'registration_cities',
            self::TTL,
            fn () => City::orderBy('name')->get(['id', 'name', 'slug'])->toArray()
        );
    }

    /**
     * Get business categories with caching strategy.
     */
    public function getBusinessCategories(): array
    {
        return Cache::remember(
            'registration_categories',
            self::TTL,
            fn () => Category::orderBy('name')->get(['id', 'name', 'slug'])->toArray()
        );
    }
}
