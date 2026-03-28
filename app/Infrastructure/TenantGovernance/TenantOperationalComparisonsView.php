<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance;

use App\Application\TenantGovernance\Contracts\TenantOperationalComparisonsViewInterface;
use App\Infrastructure\TenantGovernance\Readers\TenantOperationalComparisonReader;
use App\Models\Tenant;

final readonly class TenantOperationalComparisonsView implements TenantOperationalComparisonsViewInterface
{
    public function __construct(
        private TenantOperationalComparisonReader $reader,
    ) {}

    public function summarize(string $tenantId): array
    {
        return $this->reader->summarize(
            tenant: $this->resolveTenant($tenantId),
            formatCurrency: static fn (float $value): string => 'L. '.number_format($value, 2),
        );
    }

    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->find($tenantId);

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException("Tenant not found for operational comparisons view [{$tenantId}].");
        }

        return $tenant;
    }
}
