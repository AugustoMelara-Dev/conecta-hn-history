<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\CustomerProfile;

use App\Domain\TenantGovernance\Services\CustomerSegmentPlaybook;
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
        return 'Clientes con senal fuerte de reactivacion';
    }

    /**
     * @return array<string, string>
     */
    public function preferredChannelOptions(): array
    {
        return [
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'phone' => 'Llamada',
            'in_person' => 'Presencial',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function manualSegmentOptions(): array
    {
        return [
            'vip' => 'VIP',
            'repeat' => 'Recurrente',
            'reactivation' => 'Reactivacion',
            'booked_service' => 'Con citas',
            'active' => 'Activo',
            'prospect' => 'Prospecto',
            'new' => 'Nuevo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function lifecycleStageOptions(): array
    {
        return [
            'prospect' => 'Prospecto',
            'active' => 'Activo',
            'loyal' => 'Leal',
            'dormant' => 'Dormido',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function loyaltyTierOptions(): array
    {
        return [
            'vip' => 'VIP',
            'gold' => 'Gold',
            'silver' => 'Silver',
            'base' => 'Base',
        ];
    }

    public function nextTouchCopy(CustomerProfile $profile): string
    {
        return $this->segmentPlaybook->forProfile($profile)['next_touch'];
    }

    public function loyaltyTierCopy(CustomerProfile $profile): string
    {
        return $this->segmentPlaybook->forProfile($profile)['loyalty_tier_copy'];
    }
}
