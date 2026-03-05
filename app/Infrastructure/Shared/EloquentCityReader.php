<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\CityReader;
use App\Models\City;
use Illuminate\Support\Facades\Cache;

/**
 * EloquentCityReader - Cached Eloquent Implementation of CityReader
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE:                                               ║
 * ║  - Implements Domain Contract (CityReader)                               ║
 * ║  - Cache-Aside Pattern encapsulated in infrastructure layer              ║
 * ║  - Controller remains agnostic to caching strategy                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class EloquentCityReader implements CityReader
{
    private const CACHE_KEY = 'cities_list';

    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Get all cities with their geographic hierarchy.
     *
     * Uses Cache-Aside pattern for performance optimization.
     */
    public function getAllCities(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return City::with('municipality.department')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }
}
