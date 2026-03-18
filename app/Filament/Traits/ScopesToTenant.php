<?php

declare(strict_types=1);

namespace App\Filament\Traits;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait ScopesToTenant
 *
 * ARCHITECTURAL GOAL: DRY & Security
 * Centralizes the "Defense in Depth" logic for ensuring resources are strictly
 * scoped to the current tenant, even if the global scope fails or is bypassed.
 *
 * VISIBILITY: Respects SoftDeletes - deleted records remain hidden in listings.
 * Use withTrashed() in specific resource methods if restore functionality is needed.
 *
 * @mixin \Filament\Resources\Resource
 */
trait ScopesToTenant
{
    /**
     * Override the base query to enforce tenant isolation.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // STRICT TENANCY ENFORCEMENT
        $tenant = Filament::getTenant();
        if ($tenant instanceof Tenant) {
            $query->where('tenant_id', $tenant->getKey());
        }

        return $query;
    }
}
