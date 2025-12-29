<?php

namespace App\Observers;

use App\Models\MenuSection;
use App\Services\TenantCacheManager;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * MenuSectionObserver - Category Watchdog with Transactional Event Consistency
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSACTIONAL CONSISTENCY + PRECISION INVALIDATION                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * IMPLEMENTS: ShouldHandleEventsAfterCommit
 * Defers observer execution until after DB transaction commits, preventing
 * "Ghost Read" race conditions where concurrent processes cache stale data.
 *
 * PROBLEM SOLVED:
 * 1. Hypersensitive Invalidation: Only PUBLIC attributes trigger rotation
 * 2. Zombie Data Risk: Dual invalidation for cross-tenant migrations
 * 3. Ghost Reads: Events fire AFTER commit for consistency
 *
 * PUBLIC SURFACE AREA: ['name', 'slug', 'is_visible', 'sort_order']
 */
class MenuSectionObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Attributes that impact the public JSON representation.
     * Changes to these fields require cache invalidation.
     */
    protected const PUBLIC_ATTRIBUTES = [
        'name',
        'slug',
        'is_visible',
        'sort_order',
    ];

    /**
     * The tenant cache manager instance.
     */
    protected TenantCacheManager $cacheManager;

    /**
     * Create a new observer instance.
     */
    public function __construct(TenantCacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Handle the MenuSection "saved" event.
     *
     * TARGETED INVALIDATION LOGIC:
     * 1. Always invalidate on creation (new data)
     * 2. On update: only invalidate if PUBLIC attributes changed
     * 3. Handle cross-tenant migrations with dual invalidation
     */
    public function saved(MenuSection $menuSection): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // PRIORITY CHECK: Handle tenant migration FIRST (dual invalidation)
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->handleTenantMigration($menuSection)) {
            // Migration handled - both source and destination invalidated
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD: Skip if no tenant context
        // ═══════════════════════════════════════════════════════════════════════
        if (! $menuSection->tenant_id) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // CREATION: Always invalidate for new entities
        // ═══════════════════════════════════════════════════════════════════════
        if ($menuSection->wasRecentlyCreated) {
            $this->rotateTenantVersion($menuSection->tenant_id, 'menu_section_created', [
                'menu_section_id' => $menuSection->id,
                'menu_section_name' => $menuSection->name,
            ]);

            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // DIRTY CHECK: Only invalidate if PUBLIC attributes changed
        // ═══════════════════════════════════════════════════════════════════════
        if (! $menuSection->wasChanged(self::PUBLIC_ATTRIBUTES)) {
            // Internal/admin fields changed - no cache impact
            Log::debug('MenuSection update skipped cache invalidation (no public changes)', [
                'menu_section_id' => $menuSection->id,
                'changed_attributes' => array_keys($menuSection->getChanges()),
            ]);

            return;
        }

        // Public attribute changed - invalidate cache
        $this->rotateTenantVersion($menuSection->tenant_id, 'menu_section_public_update', [
            'menu_section_id' => $menuSection->id,
            'changed_public_attributes' => array_intersect(
                array_keys($menuSection->getChanges()),
                self::PUBLIC_ATTRIBUTES
            ),
        ]);
    }

    /**
     * Handle the MenuSection "deleted" event.
     */
    public function deleted(MenuSection $menuSection): void
    {
        if (! $menuSection->tenant_id) {
            return;
        }

        $this->rotateTenantVersion($menuSection->tenant_id, 'menu_section_deleted', [
            'menu_section_id' => $menuSection->id,
            'menu_section_name' => $menuSection->name,
        ]);
    }

    /**
     * Handle the MenuSection "restored" event.
     */
    public function restored(MenuSection $menuSection): void
    {
        if (! $menuSection->tenant_id) {
            return;
        }

        $this->rotateTenantVersion($menuSection->tenant_id, 'menu_section_restored', [
            'menu_section_id' => $menuSection->id,
        ]);
    }

    /**
     * Handle cross-tenant migration (tenant_id foreign key mutation).
     *
     * THE DUAL INVALIDATION STRATEGY:
     * When a category moves from Tenant A to Tenant B:
     * 1. Rotate Tenant B (destination) - category appears in new storefront
     * 2. Rotate Tenant A (source) - category disappears from old storefront
     *
     * This prevents "Zombie Data" where the old tenant continues serving
     * the migrated category from stale cache.
     *
     * @return bool True if migration was detected and handled, false otherwise
     */
    protected function handleTenantMigration(MenuSection $menuSection): bool
    {
        if (! $menuSection->wasChanged('tenant_id')) {
            return false;
        }

        $currentTenantId = $menuSection->tenant_id;
        $originalTenantId = $menuSection->getOriginal('tenant_id');

        // ═══════════════════════════════════════════════════════════════════════
        // DESTINATION TENANT: Category now belongs here
        // ═══════════════════════════════════════════════════════════════════════
        if ($currentTenantId) {
            $this->rotateTenantVersion($currentTenantId, 'menu_section_migrated_to', [
                'menu_section_id' => $menuSection->id,
                'from_tenant_id' => $originalTenantId,
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════════
        // SOURCE TENANT: Category must vanish from here
        // ═══════════════════════════════════════════════════════════════════════
        if ($originalTenantId && $originalTenantId !== $currentTenantId) {
            $this->rotateTenantVersion($originalTenantId, 'menu_section_migrated_from', [
                'menu_section_id' => $menuSection->id,
                'to_tenant_id' => $currentTenantId,
            ]);

            Log::info('Cross-tenant migration detected - dual invalidation executed', [
                'menu_section_id' => $menuSection->id,
                'source_tenant' => $originalTenantId,
                'destination_tenant' => $currentTenantId,
            ]);
        }

        return true;
    }

    /**
     * Rotate the cache version for a tenant with contextual logging.
     */
    private function rotateTenantVersion(int $tenantId, string $trigger, array $context = []): void
    {
        try {
            $newVersion = $this->cacheManager->rotateTenantVersion($tenantId);

            Log::debug("Tenant cache version rotated to {$newVersion}", array_merge([
                'tenant_id' => $tenantId,
                'trigger' => $trigger,
            ], $context));

        } catch (\Exception $e) {
            Log::warning('Tenant version rotation failed: '.$e->getMessage(), [
                'tenant_id' => $tenantId,
                'trigger' => $trigger,
            ]);
        }
    }
}
