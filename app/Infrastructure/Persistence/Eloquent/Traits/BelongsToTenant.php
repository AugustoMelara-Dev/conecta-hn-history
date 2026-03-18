<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Traits;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant - Security Trait
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACTIVATOR: Applies TenantScope to the Model.                           ║
 * ║  AUTOMATOR: Injects tenant_id during creation (Observer-like).          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
// Used by Eloquent models outside the Infrastructure scan path.
// @phpstan-ignore trait.unused
trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToTenant(): void
    {
        // 1. Apply Global Scope (Read Protection)
        static::addGlobalScope(new TenantScope);

        // 2. Inject Tenant ID on Creation (Write Protection)
        static::creating(function ($model) {
            // Only inject if not already set (allows override for Admin SEEDERS)
            if (! isset($model->tenant_id)) {
                $context = app(TenantContext::class);

                // BYPASS: System Mode (Console/Jobs/Admin Seeding)
                if ($context->isRoot()) {
                    return;
                }

                // This will throw if context missing, preventing accidental orphan records
                $model->tenant_id = $context->id();
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
