<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance;

use App\Application\TenantGovernance\Contracts\TenantGrowthSignalsViewInterface;
use App\Infrastructure\TenantGovernance\Readers\TenantGrowthSignalsReader;
use App\Models\Tenant;

final readonly class TenantGrowthSignalsView implements TenantGrowthSignalsViewInterface
{
    public function __construct(
        private TenantGrowthSignalsReader $reader,
    ) {}

    public function summarize(string $tenantId, string $businessType): array
    {
        return $this->reader->summarize(
            tenant: $this->resolveTenant($tenantId),
            businessType: $businessType,
        );
    }

    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->find($tenantId);

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException("Tenant not found for growth signals view [{$tenantId}].");
        }

        return $tenant;
    }
}
