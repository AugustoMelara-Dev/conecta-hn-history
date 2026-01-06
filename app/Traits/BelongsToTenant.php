<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant - Universal Trait for Multi-Tenant Models
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * SECURITY CRITICAL - AUTOMATIC TENANT ISOLATION
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * This trait provides:
 * 1. Automatic TenantScope for all SELECT queries
 * 2. Automatic tenant_id assignment on CREATE
 * 3. Tenant relationship definition
 *
 * USAGE:
 * ```php
 * class Product extends Model
 * {
 *     use BelongsToTenant;
 * }
 * ```
 *
 * SECURITY GUARANTEES:
 * - All queries filtered by tenant_id automatically
 * - New records assigned to current tenant
 * - No developer intervention required
 * - Fail-secure: returns empty if no tenant context
 *
 * BYPASS (for admin super-users):
 * ```php
 * Product::withoutGlobalScope(TenantScope::class)->get();
 * ```
 */
trait BelongsToTenant
{
    /**
     * Boot the trait - registers the global scope and model events.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Register the TenantScope for all queries
        static::addGlobalScope(new TenantScope);

        // Auto-assign tenant_id on model creation
        static::creating(function ($model) {
            // Skip if tenant_id is already set (explicitly passed)
            if ($model->tenant_id) {
                return;
            }

            // Skip in console mode (seeders, migrations)
            if (app()->runningInConsole()) {
                return;
            }

            // Resolve tenant from context
            $tenantId = static::resolveTenantIdForCreation();

            if ($tenantId) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Resolve tenant ID for new model creation.
     *
     * PRIORITY ORDER:
     * 1. Filament Panel Context
     * 2. Authenticated User's Primary Tenant
     */
    protected static function resolveTenantIdForCreation(): ?int
    {
        // Method 1: Filament Panel Context
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                $tenant = \Filament\Facades\Filament::getTenant();
                if ($tenant) {
                    return $tenant->id;
                }
            } catch (\Throwable $e) {
                // Filament not initialized
            }
        }

        // Method 2: Authenticated user's primary tenant
        if (auth()->check()) {
            $user = auth()->user();
            $primaryTenant = $user->tenants()->first();
            if ($primaryTenant) {
                return $primaryTenant->id;
            }
        }

        return null;
    }

    /**
     * Get the tenant that owns this model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant.
     *
     * Useful for explicit tenant filtering in controllers.
     * Example: Product::forTenant($tenantId)->get()
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Check if this model belongs to the given tenant.
     */
    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }

    /**
     * Verify ownership - throws exception if model doesn't belong to tenant.
     *
     * Use in controllers for explicit verification:
     * $product->verifyTenantOwnership($request->tenant_id);
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function verifyTenantOwnership(int $tenantId): void
    {
        if (! $this->belongsToTenant($tenantId)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'This resource does not belong to your organization.'
            );
        }
    }
}
