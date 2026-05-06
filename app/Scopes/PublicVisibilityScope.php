<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * PublicVisibilityScope - Context-Aware Business Visibility Filter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY & UX RATIONALE: Preventing "Over-Scoping" Anti-Pattern        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * This scope ensures the public frontend displays ONLY valid entity states:
 * - is_visible = true (not hidden by owner)
 * - is_active = true (not deactivated)
 * - is_locked = false (not soft-locked for moderation)
 *
 * CRITICAL: This scope is EXCLUSIVELY for PUBLIC-FACING routes.
 * It automatically bypasses in administrative contexts to prevent:
 * - Business owners unable to see their own draft products
 * - Admins unable to moderate hidden content
 *
 * BYPASS CONDITIONS (scope not applied):
 * 1. Console context (Artisan, seeders, queue workers)
 * 2. Filament Admin Panel (route matches admin/*)
 * 3. Filament App Panel (tenant dashboard - owner viewing own data)
 * 4. Authenticated tenant owner viewing their own products
 */
class PublicVisibilityScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 1: Console Protection (Artisan, Seeders, Queue Workers)
        // ═══════════════════════════════════════════════════════════════════════
        if (app()->runningInConsole()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 2: Filament Panel Protection (Admin & App Panels)
        // ═══════════════════════════════════════════════════════════════════════
        // Both super admins AND tenant owners need to see all products
        // in their respective dashboards for management purposes.
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isFilamentContext()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 3: Route-Based Admin Detection (Fallback)
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isAdminRoute()) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // GUARD 4: Tenant Owner Viewing Own Products (Public Storefront Routes)
        // ═══════════════════════════════════════════════════════════════════════
        if ($this->isTenantOwnerViewingOwnData($builder)) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // APPLY VISIBILITY FILTERS (Public Frontend Only)
        // ═══════════════════════════════════════════════════════════════════════
        $table = $model->getTable();

        // Filter 1: Must be visible (not hidden by owner)
        if ($this->modelHasColumn($model, 'is_visible')) {
            $builder->where("{$table}.is_visible", true);
        }

        // Filter 2: Must be active (not deactivated)
        if ($this->modelHasColumn($model, 'is_active')) {
            $builder->where("{$table}.is_active", true);
        }

        // Filter 3: Must not be locked (moderation lock)
        if ($this->modelHasColumn($model, 'is_locked')) {
            $builder->where("{$table}.is_locked", false);
        }
    }

    /**
     * Check if currently in Filament context (any panel).
     */
    protected function isFilamentContext(): bool
    {
        if (! class_exists(\Filament\Facades\Filament::class)) {
            return false;
        }

        try {
            // Filament::isServing() returns true when in any Filament panel
            return \Filament\Facades\Filament::isServing();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if current route matches admin patterns.
     */
    protected function isAdminRoute(): bool
    {
        try {
            return request()->is('admin/*') ||
                   request()->is('admin') ||
                   request()->is('app/*') ||
                   request()->is('app');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if authenticated user is the tenant owner viewing their own products.
     */
    protected function isTenantOwnerViewingOwnData(Builder $builder): bool
    {
        if (! Auth::check()) {
            return false;
        }

        // Get tenant from route parameter
        $tenant = request()->route('tenant');
        if (! $tenant) {
            return false;
        }

        // Check if current user owns this tenant
        $user = Auth::user();

        // Method 1: Direct ownership check via tenant's user relationship
        if (method_exists($tenant, 'user') && $tenant->user_id === $user->id) {
            return true;
        }

        // Method 2: User belongs to tenant check
        if (method_exists($user, 'tenants')) {
            return $user->tenants()->where('id', $tenant->id)->exists();
        }

        return false;
    }

    /**
     * Check if model has a specific column (cache-friendly).
     */
    protected function modelHasColumn(Model $model, string $column): bool
    {
        // Use fillable/guarded as a proxy for column existence
        // This avoids database schema introspection overhead
        return in_array($column, $model->getFillable()) ||
               $model->getConnection()
                   ->getSchemaBuilder()
                   ->hasColumn($model->getTable(), $column);
    }
}
