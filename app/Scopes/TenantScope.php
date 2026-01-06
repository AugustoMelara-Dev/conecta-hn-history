<?php

declare(strict_types=1);

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
        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 1: Console Context (Artisan, Seeders, Migrations, Queue Workers)
        // ═══════════════════════════════════════════════════════════════════════
        // CLI operations require unrestricted access for maintenance tasks.
        // Queue jobs should set explicit tenant context if isolation needed.
        // ═══════════════════════════════════════════════════════════════════════
        if (app()->runningInConsole()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 2: Super Admin "God Mode" Bypass
        // ═══════════════════════════════════════════════════════════════════════
        // Privilege escalation for administrators who need cross-tenant visibility.
        // This prevents the "Over-Scoping" anti-pattern where admins accidentally
        // filter themselves out of their own data.
        //
        // SECURITY: Only users with explicit super_admin role OR viewing via
        // the Admin panel (id='admin') bypass the scope. Regular sellers using
        // the App panel (id='app') remain strictly isolated.
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isSuperAdminContext()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 3: Resolve Current Tenant from Context
        // ═══════════════════════════════════════════════════════════════════════
        $tenantId = $this->resolveTenantId();

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 4: FAIL-SECURE ENFORCEMENT (CRITICAL SECURITY INVARIANT)
        // ═══════════════════════════════════════════════════════════════════════
        //
        // ╔══════════════════════════════════════════════════════════════════════╗
        // ║  SECURITY ANALYSIS: Fail-Open vs Fail-Secure                        ║
        // ╠══════════════════════════════════════════════════════════════════════╣
        // ║  PREVIOUS (VULNERABLE):                                              ║
        // ║    if ($tenantId === null) { return; }                              ║
        // ║    ↳ Returns without modifying query = SELECT * FROM table          ║
        // ║    ↳ EXPOSES ALL TENANT DATA TO UNAUTHENTICATED USERS               ║
        // ║                                                                      ║
        // ║  CURRENT (SECURE):                                                   ║
        // ║    if ($tenantId === null) { $builder->whereRaw('1 = 0'); return; } ║
        // ║    ↳ Actively sabotages query with impossible condition             ║
        // ║    ↳ GUARANTEES ZERO RECORDS RETURNED                               ║
        // ╚══════════════════════════════════════════════════════════════════════╝
        //
        // This is a MANDATORY security constraint. If we cannot determine tenant
        // context and the user is NOT a Super Admin, we MUST prevent data access.
        // Simply returning is STRICTLY PROHIBITED as it results in data leakage.
        // ═══════════════════════════════════════════════════════════════════════
        if ($tenantId === null) {
            // FAIL-SECURE: Sabotage query to return zero records
            $builder->whereRaw('1 = 0');
            
            // Log this security event for forensic analysis
            \Illuminate\Support\Facades\Log::warning(
                'TenantScope: Fail-Secure triggered - no tenant context',
                [
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                    'user_id' => auth()->id(),
                    'model' => get_class($model),
                ]
            );
            
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // APPLY TENANT ISOLATION CONSTRAINT
        // ═══════════════════════════════════════════════════════════════════════
        // Fully qualified column name prevents ambiguity in JOINs
        // ═══════════════════════════════════════════════════════════════════════
        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }

    /**
     * Check if current context is Super Admin with cross-tenant privileges.
     *
     * BYPASS CONDITIONS (ANY):
     * 1. User has 'super_admin' role column value
     * 2. User is in Admin panel (Filament panel id='admin')
     * 3. User has master admin email (hardcoded super-user)
     */
    protected function isSuperAdminContext(): bool
    {
        // Must be authenticated
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Master admin always has God Mode
        if ($user->email === 'admin@conectahn.com') {
            return true;
        }

        // Role-based check (using role column, not Spatie)
        if (property_exists($user, 'role') && $user->role === 'super_admin') {
            return true;
        }

        // Filament Admin Panel check (id='admin' panel only)
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $panel = \Filament\Facades\Filament::getCurrentPanel();
                if ($panel && $panel->getId() === 'admin') {
                    return true;
                }
            } catch (\Throwable $e) {
                // Panel not initialized
            }
        }

        return false;
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
