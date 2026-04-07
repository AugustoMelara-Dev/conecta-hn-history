<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance;

use App\Application\TenantGovernance\Contracts\TenantJourneyAnalyticsViewInterface;
use App\Infrastructure\TenantGovernance\Readers\TenantJourneyAnalyticsReader;
use App\Models\Tenant;

final readonly class TenantJourneyAnalyticsView implements TenantJourneyAnalyticsViewInterface
{
    public function __construct(
        private TenantJourneyAnalyticsReader $reader,
    ) {}

    public function summarize(string $tenantId): array
    {
        return $this->reader->summarize($this->resolveTenant($tenantId));
    }

    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->find($tenantId);

        if (! $tenant instanceof Tenant) {
            throw new \RuntimeException("Tenant not found for journey analytics view [{$tenantId}].");
        }

        return $tenant;
    }
}
