<?php

declare(strict_types=1);

namespace App\Support\Migrations;

final class TenantLegacySnapshotStateNormalizer
{
    private const LEGACY_PAID_PLAN_FLAG = 'is_pro';

    /**
     * @param  array<string, mixed>  $state
     */
    public function looksLikeTenantSnapshot(array $state): bool
    {
        return array_key_exists('id', $state)
            && array_key_exists('domain', $state)
            && array_key_exists('business_type', $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function hasLegacyPaidPlanFlag(array $state): bool
    {
        return array_key_exists(self::LEGACY_PAID_PLAN_FLAG, $state);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function requiresNormalization(array $state): bool
    {
        if (! $this->looksLikeTenantSnapshot($state) || ! $this->hasLegacyPaidPlanFlag($state)) {
            return false;
        }

        return ! array_key_exists('plan_id', $state)
            || $state['plan_id'] === null
            || $state['plan_id'] === '';
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function normalize(array $state): array
    {
        if (! $this->requiresNormalization($state)) {
            return $state;
        }

        $normalized = $state;
        $normalized['plan_id'] = (bool) $state[self::LEGACY_PAID_PLAN_FLAG] ? 'pro' : 'semilla';

        unset($normalized[self::LEGACY_PAID_PLAN_FLAG]);

        return $normalized;
    }
}
