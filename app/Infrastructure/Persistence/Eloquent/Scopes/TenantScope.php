<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Scopes;

use App\Domain\Shared\Exceptions\TenantContextMissingException;
use App\Domain\Shared\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope - Eloquent Query Interceptor
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ENFORCER: Injects `WHERE tenant_id = X` into ALL queries.              ║
 * ║  GLOBAL: Applies to all models using `BelongsToTenant` trait.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Resolve Context
        try {
            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            // BYPASS: System Mode (Console, Jobs, Super Admin)
            if ($context->isRoot()) {
                return;
            }

            $tenantId = $context->id();

            $builder->where($model->getTable().'.tenant_id', '=', $tenantId);

        } catch (TenantContextMissingException $e) {
            // FAIL SECURE: Context Missing.
            // Explicitly re-throw to comply with Zero Trust Architecture.
            throw $e;
        }
    }
}
