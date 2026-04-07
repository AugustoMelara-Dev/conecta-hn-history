<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * RequestTenantContext - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Resolve Tenant ID from the current HTTP Request/User.
 */
final class RequestTenantContext implements TenantContext
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getTenantUuid(): string
    {
        $tenant = $this->resolveTenantModel();

        if ($tenant instanceof Tenant) {
            return (string) $tenant->getKey();
        }

        $user = $this->request->user();

        if (! $user) {
            throw new \RuntimeException('Tenant Context Error: User not authenticated.');
        }

        $tenantUuid = $user->tenant_uuid ?? $user->tenant_id ?? ($user->tenant->uuid ?? null) ?? ($user->tenant?->getKey() ?? null);

        if (! $tenantUuid) {
            throw new \RuntimeException('Tenant Context Error: User has no associated tenant UUID.');
        }

        return (string) $tenantUuid;
    }

    /**
     * {@inheritDoc}
     */
    public function getTenantId(): int
    {
        // Try resolving from authenticated user
        // We use dynamic property access to avoid hard import of User model class
        // but strictly check for existence.

        $user = $this->request->user();

        if (! $user) {
            throw new \RuntimeException('Tenant Context Error: User not authenticated.');
        }

        $tenantId = $user->tenant_id ?? null;

        if (! $tenantId) {
            throw new \RuntimeException('Tenant Context Error: User has no associated tenant.');
        }

        return (int) $tenantId;
    }

    /**
     * {@inheritDoc}
     */
    public function getTenantData(?array $only = null): array
    {
        $tenant = $this->resolveTenantModel();

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException('Tenant Context Error: Tenant not available.');
        }

        $payload = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan_type' => $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? Tenant::PLAN_SEMILLA),
            'is_paid_plan' => in_array(
                (string) ($tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? Tenant::PLAN_SEMILLA)),
                [TenantPlan::PRO->value, Tenant::PLAN_CRECIMIENTO, Tenant::PLAN_COSECHA, 'enterprise'],
                true
            ),
        ];

        if ($only !== null) {
            return array_intersect_key($payload, array_flip($only));
        }

        return $payload;
    }

    private function resolveTenantModel(): ?Tenant
    {
        if (app()->bound('currentTenant')) {
            $tenant = app('currentTenant');

            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        $user = $this->request->user();

        return $user?->tenant instanceof Tenant ? $user->tenant : null;
    }
}
