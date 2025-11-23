<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class PublicVisibilityScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * SOFT-LOCKING Pattern:
     * 1. Filters out locked products for public users
     * 2. Bypasses filter for:
     *    - Admin panel (Filament)
     *    - Tenant owner viewing their own products
     *    - Console commands
     */
    public function apply(Builder $builder, Model $model)
    {
        // Guard 1: Console protection
        if (app()->runningInConsole()) {
            return;
        }

        // Guard 2: Admin Panel protection (Filament)
        if ($this->isAdminPanel()) {
            return;
        }

        // Guard 3: Tenant Owner protection
        if ($this->isTenantOwner($builder)) {
            return;
        }

        // Apply the visibility filter (only unlocked products)
        $builder->where($model->getTable() . '.is_locked', false);
    }

    /**
     * Check if current request is from Filament Admin Panel
     */
    protected function isAdminPanel(): bool
    {
        // Method 1: Check route prefix
        if (request()->is('admin/*') || request()->is('admin')) {
            return true;
        }

        // Method 2: Check Filament context (if Filament is loaded)
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                return \Filament\Facades\Filament::getCurrentPanel() !== null;
            } catch (\Throwable $e) {
                // Filament not in context
            }
        }

        return false;
    }

    /**
     * Check if authenticated user is the tenant owner
     */
    protected function isTenantOwner(Builder $builder): bool
    {
        if (!Auth::check()) {
            return false;
        }

        // Check if we're querying products through a tenant relationship
        // This allows owners to see their own locked products
        $tenant = request()->route('tenant');
        
        if ($tenant && Auth::user()->id === optional($tenant)->user_id) {
            return true;
        }

        return false;
    }
}
