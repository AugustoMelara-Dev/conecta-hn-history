<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Support\Str;

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

        $resolved = [
            'key' => $segmentKey,
            'label' => (string) ($segment['label'] ?? 'Nuevo'),
            'promise' => (string) ($segment['promise'] ?? 'Cliente en seguimiento.'),
            'next_touch' => (string) ($segment['next_touch'] ?? 'Conviene hacer seguimiento en el proximo turno.'),
            'loyalty_move' => (string) ($segment['loyalty_move'] ?? 'Empuja una siguiente accion comercial con incentivo claro.'),
            'response_sla_hours' => (int) ($segment['response_sla_hours'] ?? 24),
            'loyalty_tier_copy' => (string) ($tiers[$loyaltyTier] ?? $tiers['base'] ?? 'Cliente en etapa inicial de fidelidad.'),
        ];

        $tenantRecord = $profile->tenant()->withoutGlobalScopes()->first();
        $tenant = $tenantRecord instanceof Tenant ? $tenantRecord : null;

        if (! $tenant instanceof Tenant) {
            return $resolved;
        }

        $manifestCrm = app(RubroManifestReaderInterface::class)->crmForTenant($tenant);
        $manifestSegmentKey = $this->resolveManifestSegmentKey($manifestCrm, $segmentKey);
        $journey = collect((array) data_get($manifestCrm, "journeys.{$manifestSegmentKey}.journey", []))
            ->filter(static fn (mixed $step): bool => is_array($step))
            ->values();

        if ($journey->isEmpty()) {
            return $resolved;
        }

        /** @var array<string, mixed>|null $firstStep */
        $firstStep = $journey->first();
        /** @var array<string, mixed>|null $lastStep */
        $lastStep = $journey->last();

        $resolved['label'] = Str::of($manifestSegmentKey)->replace('_', ' ')->title()->value();
        $resolved['promise'] = (string) data_get($firstStep, 'copy', $resolved['promise']);
        $resolved['next_touch'] = (string) data_get($firstStep, 'title', $resolved['next_touch']);
        $resolved['loyalty_move'] = (string) data_get($lastStep, 'copy', $resolved['loyalty_move']);

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $manifestCrm
     */
    private function resolveManifestSegmentKey(array $manifestCrm, string $segmentKey): string
    {
        $journeys = (array) ($manifestCrm['journeys'] ?? []);
        $normalized = trim($segmentKey) !== '' ? trim($segmentKey) : 'new';

        if (array_key_exists($normalized, $journeys)) {
            return $normalized;
        }

        $aliases = [
            'new' => 'prospect',
            'lead' => 'prospect',
            'active' => 'repeat',
            'vip' => 'repeat',
            'booked' => 'booked_service',
            'booked_service' => 'booked_service',
        ];
        $alias = $aliases[$normalized] ?? null;

        if (is_string($alias) && array_key_exists($alias, $journeys)) {
            return $alias;
        }

        $preferred = array_values(array_filter(
            (array) ($manifestCrm['preferred_segments'] ?? []),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        foreach ($preferred as $candidate) {
            if (array_key_exists($candidate, $journeys)) {
                return $candidate;
            }
        }

        return $normalized;
    }
}
