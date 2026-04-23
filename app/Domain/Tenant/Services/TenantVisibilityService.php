<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Services;

use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;

/**
 * TenantVisibilityService - Gatekeeper Logic
 *
 * Enforces public visibility rules for tenants.
 * Throws domain exceptions instead of HTTP-specific abort() calls.
 * HTTP response mapping is the responsibility of the Infrastructure exception handler.
 */
final class TenantVisibilityService
{
    /**
     * Ensure tenant is visible to public visitors.
     *
     * @throws TenantNotFoundException
     */
    public function check(object $tenant): void
    {
        // Layer 1: Administrative verification status
        if (($tenant->status ?? null) !== 'approved') {
            throw TenantNotFoundException::withId((string) $tenant->id);
        }

        // Layer 2: Operational status
        if (! $tenant->is_active) {
            throw TenantNotFoundException::withId((string) $tenant->id);
        }

        // Layer 3: Subscription status (no morosos in public directory)
        if (! in_array($tenant->subscription_status, ['active', 'trial'], true)) {
            throw TenantNotFoundException::withId((string) $tenant->id);
        }

        // Layer 3: Claim verification (public discovery only for verified businesses)
        if (($tenant->verification_status ?? 'provisional') !== 'verified') {
            throw TenantNotFoundException::withId((string) $tenant->id);
        }

        // Layer 4: Publication paused due to dispute/safety review
        if (! empty($tenant->publication_paused_at)) {
            throw TenantNotFoundException::withId((string) $tenant->id);
        }
    }
}
