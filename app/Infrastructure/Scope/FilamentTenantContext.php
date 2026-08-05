<?php

declare(strict_types=1);

namespace App\Infrastructure\Scope;

use App\Contracts\Scope\TenantContextInterface;

/**
 * FilamentTenantContext - Infrastructure Adapter for Filament Panel
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  HEXAGONAL ARCHITECTURE: INFRASTRUCTURE ADAPTER                         ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  THIS IS THE ONLY CLASS IN THE ENTIRE CODEBASE AUTHORIZED TO:           ║
 * ║    - Import Filament\Facades\Filament                                   ║
 * ║    - Call Filament::getTenant()                                         ║
 * ║                                                                          ║
 * ║  All other classes (Models, Scopes, Traits) MUST use                    ║
 * ║  TenantContextInterface instead of direct Filament calls.               ║
 * ║                                                                          ║
 * ║  REGISTRATION: Bound in InfrastructureServiceProvider as singleton      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class FilamentTenantContext implements TenantContextInterface
{
    /**
     * Resolve tenant ID from Filament's active panel context.
     *
     * RESOLUTION PRIORITY:
     * 1. Filament Panel Tenant (if Filament is initialized)
     * 2. Authenticated User's Primary Tenant (fallback)
     * 3. null (no context available)
     */
    public function getTenantId(): ?string
    {
        // ═══════════════════════════════════════════════════════════════════
        // METHOD 1: Filament Panel Context (Primary)
        // ═══════════════════════════════════════════════════════════════════
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                if ($tenant !== null) {
                    return (string) $tenant->id;
                }
            } catch (\Throwable) {
                // Filament not initialized (CLI, Queue, etc.)
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // METHOD 2: Authenticated User's Primary Tenant (Fallback)
        // ═══════════════════════════════════════════════════════════════════
        if (auth()->check()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();

            // User->tenants() returns HasMany (user owns tenants)
            $primaryTenant = $user->tenants()->first();
            if ($primaryTenant !== null) {
                return (string) $primaryTenant->id;
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // METHOD 3: No Context Available
        // ═══════════════════════════════════════════════════════════════════
        return null;
    }
}
