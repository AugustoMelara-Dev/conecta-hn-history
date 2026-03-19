<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Traits;

use App\Domain\Shared\Services\TenantContext;

/**
 * Allows trusted tenant assignment without re-opening mass-assignment holes.
 *
 * `tenant_id` remains excluded from `$fillable`, but internal system contexts
 * such as tests, console commands, and root tenant context may still seed it
 * explicitly when creating records.
 */
trait AllowsTrustedTenantAssignment
{
    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        $fillable = parent::getFillable();

        if ($this->allowsTrustedTenantAssignment() && ! in_array('tenant_id', $fillable, true)) {
            $fillable[] = 'tenant_id';
        }

        return $fillable;
    }

    private function allowsTrustedTenantAssignment(): bool
    {
        if (! function_exists('app')) {
            return false;
        }

        if (app()->runningInConsole() || app()->environment('testing')) {
            return true;
        }

        if (! app()->bound(TenantContext::class)) {
            return false;
        }

        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);

        return $tenantContext->isRoot();
    }
}
