<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant - "Sovereignty by Default"
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO-TOUCH CONFIGURATION TRAIT                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * 1. Boots the TenantScope (Reading protection).
 * 2. Auto-hydrates `tenant_id` on creation (Writing protection).
 * 3. Defines the relationship.
 */
trait BelongsToTenant
{
    /**
     * The "Boot" method of the Trait.
     * Laravel automatically calls this when a model using the trait is booted.
     */
    public static function bootBelongsToTenant(): void
    {
        // 1. READ PROTECTION: Apply the Global Scope
        static::addGlobalScope(new TenantScope);

        // 2. WRITE PROTECTION: Auto-assign tenant_id before creation
        static::creating(function ($model) {
            // If tenant_id is already set (e.g. by seeding or manual override), do nothing.
            if ($model->tenant_id) {
                return;
            }

            // Only auto-assign if we are in a valid Filament Tenant Context
            $tenant = Filament::getTenant();

            if ($tenant) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    /**
     * Define the relationship to the Tenant model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
