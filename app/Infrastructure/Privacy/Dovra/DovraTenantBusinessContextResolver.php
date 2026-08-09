<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Models\Tenant;
use App\Scopes\LocationScope;

/**
 * Resolves the small, policy-facing business context used by Dovra ingress.
 *
 * This is deliberately read-only and derives authority from the canonical
 * tenant record and its active category. HTTP/request hints are not accepted.
 */
final class DovraTenantBusinessContextResolver
{
    public function resolvePolicyBusinessType(string $tenantId): ?string
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return null;
        }

        try {
            /**
             * Tenant has a location scope which is not the business-type
             * authority. Bypass only that read scope; SoftDeletes remains in
             * force so deleted tenants fail closed.
             */
            $tenant = Tenant::query()
                // INFRA_TENANT_FILTER: only LocationScope is bypassed and the canonical tenant UUID remains mandatory.
                ->withoutGlobalScope(LocationScope::class)
                ->whereKey($tenantId)
                ->first(['id', 'business_type', 'category_id']);
        } catch (\Throwable) {
            return null;
        }

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $businessType = BusinessType::tryFrom((string) $tenant->getRawOriginal('business_type'));
        if (! $businessType instanceof BusinessType) {
            return null;
        }

        if ($businessType !== BusinessType::SERVICE) {
            return $businessType->value;
        }

        $activeCategorySlug = null;
        if ($tenant->category_id !== null) {
            try {
                $activeCategorySlug = $tenant->category()
                    ->where('is_active', true)
                    ->value('slug');
            } catch (\Throwable) {
                return null;
            }
        }

        if (strtolower((string) $activeCategorySlug) === 'salud') {
            return 'clinic';
        }

        return $businessType->value;
    }
}
