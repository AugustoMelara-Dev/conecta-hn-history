<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CityObserver - Event-Driven Cache Invalidation
 *
 * ARCHITECTURE: Decoupled cache management via Observer Pattern.
 * Triggered on ANY data modification (API, Seeders, Artisan commands).
 *
 * CACHE KEYS INVALIDATED:
 * - registration_cities: Used by the tenant registration flow for dropdowns
 * - cities_list: Legacy cache key for backwards compatibility
 *
 * COMPLIANCE: Strong data consistency across registration flows.
 */
class CityObserver
{
    /**
     * Handle the City "saved" event (covers both create and update).
     *
     * @param  City  $city  The city instance that was saved
     */
    public function saved(City $city): void
    {
        $this->invalidateRegistrationCache($city, 'saved');
    }

    /**
     * Handle the City "deleted" event.
     *
     * @param  City  $city  The city instance that was deleted
     */
    public function deleted(City $city): void
    {
        $this->invalidateRegistrationCache($city, 'deleted');
    }

    /**
     * Invalidate registration-related caches.
     *
     * DEFENSIVE: Wrapped in try-catch to prevent cache failures
     * from breaking data operations.
     *
     * @param  City  $city  The city instance
     * @param  string  $event  The event type for logging
     */
    private function invalidateRegistrationCache(City $city, string $event): void
    {
        try {
            // Primary registration cache key
            Cache::forget('registration_cities');
            // Legacy cache key for backwards compatibility
            Cache::forget('cities_list');

            Log::info('CityObserver: Cache invalidated', [
                'event' => $event,
                'city_id' => $city->id,
                'city_name' => $city->name,
            ]);
        } catch (\Exception $e) {
            // Cache failure should not break data operations
            Log::warning('CityObserver: Cache invalidation failed', [
                'error' => $e->getMessage(),
                'city_id' => $city->id,
            ]);
        }
    }
}
