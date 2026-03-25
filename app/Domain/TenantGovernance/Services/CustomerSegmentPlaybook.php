<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Models\CustomerProfile;

class CustomerSegmentPlaybook
{
    /**
     * @return array{key: string, label: string, promise: string, next_touch: string, loyalty_move: string, response_sla_hours: int, loyalty_tier_copy: string}
     */
    public function forProfile(CustomerProfile $profile): array
    {
        $segmentKey = (string) ($profile->manual_segment ?: $profile->customer_segment ?: 'new');
        $segments = (array) config('customer-segments.segments', []);
        $tiers = (array) config('customer-segments.loyalty_tiers', []);
        $segment = (array) ($segments[$segmentKey] ?? $segments['new'] ?? []);
        $loyaltyTier = (string) ($profile->loyalty_tier ?: 'base');

        return [
            'key' => $segmentKey,
            'label' => (string) ($segment['label'] ?? 'Nuevo'),
            'promise' => (string) ($segment['promise'] ?? 'Cliente en seguimiento.'),
            'next_touch' => (string) ($segment['next_touch'] ?? 'Conviene hacer seguimiento en el proximo turno.'),
            'loyalty_move' => (string) ($segment['loyalty_move'] ?? 'Empuja una siguiente accion comercial con incentivo claro.'),
            'response_sla_hours' => (int) ($segment['response_sla_hours'] ?? 24),
            'loyalty_tier_copy' => (string) ($tiers[$loyaltyTier] ?? $tiers['base'] ?? 'Cliente en etapa inicial de fidelidad.'),
        ];
    }
}
