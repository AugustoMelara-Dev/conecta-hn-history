<?php

namespace App\Filament\Traits;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Trait ScopesToTenant
 * 
 * ARCHITECTURAL GOAL: DRY & Security
 * Centralizes the "Defense in Depth" logic for ensuring resources are strictly
 * scoped to the current tenant, even if the global scope fails or is bypassed.
 */
trait ScopesToTenant
{
    /**
     * Override the base query to enforce tenant isolation.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Apply SoftDeletes if the model uses it
        if (in_array(SoftDeletingScope::class, class_uses_recursive(static::getModel()))) {
             $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        }

        // STRICT TENANCY ENFORCEMENT
        $tenant = Filament::getTenant();
        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query;
    }
}
