<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Identity\Enums\UserRole;
use App\Events\CatalogPublishedChanged;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantCacheManager;
use GdImage;
use Illuminate\Support\Facades\Log;

/**
 * TenantObserver - Lifecycle Management & Cache Orchestration
 *
 * Responsibilities:
 * 1. Role assignment on tenant creation (using UserRole Enum)
 * 2. Reactive cache invalidation on updates
 * 3. Blurhash generation for images
 */
class TenantObserver
{
    /**
     * Handle the Tenant "created" event.
     *
     * Assigns the TENANT_OWNER role to the tenant owner upon business creation.
     * Uses strict Enum comparison for type safety.
     */
    public function created(Tenant $tenant): void
    {
        $user = $tenant->user;

        // Type-safe comparison using Enum
        if ($user instanceof User && $user->role !== UserRole::TENANT_OWNER) {
            // Use Enum value for DB compatibility
            $user->update(['role' => UserRole::TENANT_OWNER]);
        }
    }

    /**
     * Handle the Tenant "updated" event.
     *
     * CACHE INVALIDATION: Critical for data consistency.
     * When a tenant updates their profile, we immediately rotate
     * their cache version, invalidating all downstream cached views.
     *
     * Performance: O(1) invalidation via version rotation.
     */
    public function updated(Tenant $tenant): void
    {
        try {
            $this->getCacheManager()->rotateTenantVersion($tenant->id);
        } catch (\Exception $e) {
            Log::warning("Cache invalidation failed for tenant {$tenant->id}: ".$e->getMessage());
        }

        if ($tenant->wasChanged(['status', 'is_active', 'subscription_status', 'city_id', 'category_id', 'slug'])) {
            CatalogPublishedChanged::dispatch(
                tenantId: (string) $tenant->getKey(),
                source: 'tenant_observer',
            );
        }
    }

    /**
     * Handle the Tenant "saved" event.
     *
     * Auto-generates Blurhash when logo changes for progressive image loading.
     */
    public function saved(Tenant $tenant): void
    {
        if ($tenant->isDirty('logo_path') && ! empty($tenant->logo_path)) {
            $fullPath = storage_path('app/public/'.$tenant->logo_path);

            if (file_exists($fullPath)) {
                try {
                    $image = imagecreatefromstring(file_get_contents($fullPath));

                    if ($image !== false) {
                        $hash = \kornrunner\Blurhash\Blurhash::encode($this->toPixelMatrix($image), 4, 3);
                        $tenant->updateQuietly(['blur_hash' => $hash]);
                        imagedestroy($image);
                    }
                } catch (\Exception $e) {
                    Log::error("Blurhash generation failed for tenant {$tenant->id}: ".$e->getMessage());
                }
            }
        }
    }

    /**
     * Handle the Tenant "deleted" event.
     *
     * Invalidate cache to prevent stale data from being served.
     */
    public function deleted(Tenant $tenant): void
    {
        try {
            $this->getCacheManager()->forgetVersion($tenant->id);
        } catch (\Exception $e) {
            Log::warning("Cache cleanup failed for deleted tenant {$tenant->id}: ".$e->getMessage());
        }

        CatalogPublishedChanged::dispatch(
            tenantId: (string) $tenant->getKey(),
            source: 'tenant_deleted',
        );
    }

    /**
     * Handle the Tenant "restored" event.
     */
    public function restored(Tenant $tenant): void
    {
        try {
            $this->getCacheManager()->rotateTenantVersion($tenant->id);
        } catch (\Exception $e) {
            Log::warning("Cache rotation failed for restored tenant {$tenant->id}: ".$e->getMessage());
        }

        CatalogPublishedChanged::dispatch(
            tenantId: (string) $tenant->getKey(),
            source: 'tenant_restored',
        );
    }

    /**
     * Handle the Tenant "force deleted" event.
     */
    public function forceDeleted(Tenant $tenant): void
    {
        try {
            $this->getCacheManager()->forgetVersion($tenant->id);
        } catch (\Exception $e) {
            Log::warning("Cache cleanup failed for force-deleted tenant {$tenant->id}: ".$e->getMessage());
        }
    }

    /**
     * Resolve the TenantCacheManager from the service container.
     *
     * DRY PATTERN: Centralizes dependency resolution.
     * Observers don't support constructor DI, so we use the container.
     */
    private function getCacheManager(): TenantCacheManager
    {
        return app(TenantCacheManager::class);
    }

    /**
     * @return array<int, array<int, array{0: int, 1: int, 2: int}>>
     */
    private function toPixelMatrix(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $pixels = [];

        for ($y = 0; $y < $height; $y++) {
            $row = [];

            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $row[] = [
                    ($rgb >> 16) & 0xFF,
                    ($rgb >> 8) & 0xFF,
                    $rgb & 0xFF,
                ];
            }

            $pixels[] = $row;
        }

        return $pixels;
    }
}
