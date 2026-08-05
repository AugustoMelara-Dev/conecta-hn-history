<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Traits;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Persistence\Eloquent\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToTenant - Security Trait
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACTIVATOR: Applies TenantScope to the Model.                           ║
 * ║  AUTOMATOR: Injects tenant_id during creation (Observer-like).          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
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
            $context = app(TenantContext::class);

            // BYPASS: System Mode (Console/Jobs/Admin Seeding)
            if ($context->isRoot()) {
                return;
            }

            if (isset($model->tenant_id) && ! $context->isInitialized()) {
                return;
            }

            // This will throw if context is missing, preventing accidental orphan records.
            $tenantId = $context->id();

            if (! isset($model->tenant_id)) {
                $model->tenant_id = $tenantId;

                return;
            }

            if ((string) $model->tenant_id !== (string) $tenantId) {
                throw new \LogicException('Tenant-owned models cannot be created for a different tenant than the active context.');
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
