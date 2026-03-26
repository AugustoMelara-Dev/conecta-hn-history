<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;
use App\Models\Tenant;

final class TenantCustomerProfilePresentationReader
{
    public function __construct(
        private readonly CustomerSegmentPlaybook $segmentPlaybook,
    ) {}

    public function navigationBadgeCount(Tenant $tenant): int
    {
        return CustomerProfile::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('reactivation_score', '>=', 65)
            ->count();
    }

    public function navigationBadgeTooltip(): string
    {
        return 'Clientes con señal fuerte de reactivación';
    }

    public function nextTouchCopy(CustomerProfile $profile): string
    {
        return (string) ($this->segmentPlaybook->forProfile($profile)['next_touch'] ?? 'Sin siguiente toque sugerido.');
    }

    public function loyaltyTierCopy(CustomerProfile $profile): string
    {
        return (string) ($this->segmentPlaybook->forProfile($profile)['loyalty_tier_copy'] ?? 'Sin lectura de fidelidad disponible.');
    }
}
