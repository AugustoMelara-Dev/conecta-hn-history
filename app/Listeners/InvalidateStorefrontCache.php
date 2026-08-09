<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Shared\Contracts\TenantAwareEvent;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * InvalidateStorefrontCache
 *
 * Event-Driven Cache Invalidation for Storefront.
 * Listens for domain events that affect proper microsite display.
 */
final class InvalidateStorefrontCache
{
    /**
     * Handle the event.
     */
    public function handle(TenantAwareEvent $event): void
    {
        // 1. Strict Contract: We know it has tenantId()
        $tenantId = $event->tenantId();

        if ($tenantId === 0) { // Assuming 0 is invalid or check VO rules
            return;
        }

        // 2. Resolve Tenant Slug for Cache Key
        // Note: Ideally the event would carry the slug if it's needed for cache keys,
        // but re-hydrating Tenant for the cleanup is acceptable in async listeners.
        // If this is synchronous, it adds a query.
        $tenant = Tenant::find($tenantId);

        if ($tenant && $tenant->slug) {
            Cache::forget("storefront:{$tenant->slug}");
        }
    }
}
