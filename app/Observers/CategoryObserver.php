<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CategoryObserver - Event-Driven Cache Invalidation
 *
 * ARCHITECTURE: Decoupled cache management via Observer Pattern.
 * Triggered on ANY data modification (API, Seeders, Artisan commands).
 *
 * CACHE KEYS INVALIDATED:
 * - registration_categories: Used by RegisterTenantController for dropdown
 *
 * COMPLIANCE: Strong data consistency across registration flows.
 */
class CategoryObserver
{
    /**
     * Handle the Category "saved" event (covers both create and update).
     *
     * @param Category $category The category instance that was saved
     */
    public function saved(Category $category): void
    {
        $this->invalidateRegistrationCache($category, 'saved');
    }

    /**
     * Handle the Category "deleted" event.
     *
     * @param Category $category The category instance that was deleted
     */
    public function deleted(Category $category): void
    {
        $this->invalidateRegistrationCache($category, 'deleted');
    }

    /**
     * Invalidate registration-related caches.
     *
     * DEFENSIVE: Wrapped in try-catch to prevent cache failures
     * from breaking data operations.
     *
     * @param Category $category The category instance
     * @param string $event The event type for logging
     */
    private function invalidateRegistrationCache(Category $category, string $event): void
    {
        try {
            Cache::forget('registration_categories');

            Log::info('CategoryObserver: Cache invalidated', [
                'event' => $event,
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);
        } catch (\Exception $e) {
            // Cache failure should not break data operations
            Log::warning('CategoryObserver: Cache invalidation failed', [
                'error' => $e->getMessage(),
                'category_id' => $category->id,
            ]);
        }
    }
}
