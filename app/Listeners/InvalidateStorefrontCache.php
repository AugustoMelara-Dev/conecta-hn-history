<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Shared\Contracts\TenantAwareEvent;
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
        // 1. Strict Contract: Extract UUID string from TenantId Value Object
        $tenantUuid = $event->tenantId()->toString();

        // 2. Resolve Tenant Slug for Cache Key
        // Note: Ideally the event would carry the slug if it's needed for cache keys,
        // but re-hydrating Tenant for the cleanup is acceptable in async listeners.
        // If this is synchronous, it adds a query.
        $tenant = \App\Models\Tenant::find($tenantUuid);

        if ($tenant && $tenant->slug) {
            Cache::forget("storefront:{$tenant->slug}");
        }
    }
}
