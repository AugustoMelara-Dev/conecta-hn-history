<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

/**
 * TenantScope - "The Great Filter" via Filament Context
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL SECURITY PATTERN: INVISIBLE WALLS                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Checks if we are inside a Filament Tenant Context. If so, it FORCIBLY
 * injects a `where('tenant_id', ...)` clause into EVERY query.
 *
 * This means `Product::all()` becomes `SELECT * FROM products WHERE tenant_id = 99`.
 * The developer CANNOT forget to filter. It happens at the ORM driver level.
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 1. Guard: Only apply if we have an authenticated user/context
        if (! auth()->check()) {
            return;
        }

        // 2. Guard: Only apply if Filament detects a Tenant context
        // This is critical. Filament::getTenant() returns null if we are
        // in a non-tenant-aware panel or outside the admin panel.
        $tenant = Filament::getTenant();

        if ($tenant) {
            // 3. The Injection
            // We use the tenant's ID from Filament context as the Single Source of Truth.
            $builder->where('tenant_id', $tenant->id);
            
            // Optional: Log for extremely paranoid audit trails (debug only)
            // Log::debug("TenantScope Applied: Filtering {$model->getTable()} for Tenant {$tenant->id}");
        }
    }
}
