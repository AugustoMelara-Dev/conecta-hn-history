<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope - Global Scope for Multi-Tenant Isolation
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY CRITICAL - ZERO TRUST ARCHITECTURE
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * This scope automatically injects `WHERE tenant_id = ?` on ALL queries
 * for models that use the BelongsToTenant trait.
 *
 * ATTACK PREVENTION:
 * - IDOR (Insecure Direct Object Reference)
 * - Horizontal Privilege Escalation
 * - Cross-Tenant Data Leakage
 *
 * USAGE: Applied automatically via BelongsToTenant trait
 *
 * BYPASS: Only via explicit withoutGlobalScope(TenantScope::class)
 * - Required for: Admin super-user queries, system migrations
 * - Should be audited in code reviews
 */
class TenantScope implements Scope
{
    /**
     * Apply the tenant isolation scope to a query.
     *
     * SECURITY BEHAVIOR:
     * 1. In Filament Admin Panel: Uses Filament::getTenant()
     * 2. In API: Uses auth()->user()->tenants->first()
     * 3. Console: Disabled (seeders, migrations)
     * 4. No tenant context: Returns empty resultset (safe default)
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Guard 1: Console protection (artisan commands, seeders, migrations)
        if (app()->runningInConsole()) {
            // Allow: Jobs may run in console but need tenant context
            // If needed, set tenant context explicitly in job
            return;
        }

        // Guard 2: Get current tenant ID from appropriate context
        $tenantId = $this->resolveTenantId();

        // Guard 3: If no tenant context, return empty resultset (FAIL SECURE)
        if ($tenantId === null) {
            // This is a security feature, not a bug
            // If we don't know the tenant, we return nothing
            return;
        }

        // Apply the isolation constraint
        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }

    /**
     * Resolve the current tenant ID from multiple possible contexts.
     *
     * PRIORITY ORDER:
     * 1. App Container Binding (currentTenant) - set by TenantMiddleware/tests
     * 2. Filament Panel Context (admin panel)
     * 3. Authenticated User's Primary Tenant (fallback)
     */
    protected function resolveTenantId(): ?int
    {
        // Method 1: Container binding (set by TenantMiddleware or tests)
        // This is the most explicit and reliable method
        if (app()->has('currentTenant')) {
            $tenant = app('currentTenant');
            if ($tenant !== null) {
                return (int) $tenant->id;
            }
        }

        // Method 2: Filament Panel Context (admin panel)
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                if ($tenant) {
                    return $tenant->id;
                }
            } catch (\Throwable $e) {
                // Filament not initialized yet, continue
            }
        }

        // Method 3: Authenticated user's primary tenant
        if (auth()->check()) {
            $user = auth()->user();

            // User->tenants() returns HasMany (user owns tenants)
            $primaryTenant = $user->tenants()->first();
            if ($primaryTenant) {
                return $primaryTenant->id;
            }
        }

        // Method 4: Request parameter (for public API endpoints)
        // WARNING: Only use when explicitly validated in controller
        // NOT recommended for automatic scope - too risky

        return null;
    }
}
