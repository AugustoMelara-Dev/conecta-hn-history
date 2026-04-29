<?php

declare(strict_types=1);

namespace App\Support\Release;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use App\Settings\GeneralSettings;

final class FeatureRolloutManager
{
    public function enabled(string $key, ?Tenant $tenant = null): bool
    {
        $rollout = $this->rolloutFor($key);

        if ($rollout === null || ! (bool) ($rollout['enabled'] ?? false)) {
            return false;
        }

        $tenantSlugs = $this->normalizeList($rollout['tenant_slugs'] ?? []);
        $plans = $this->normalizeList($rollout['plans'] ?? []);
        $businessTypes = $this->normalizeList($rollout['business_types'] ?? []);
        $hasTargets = $tenantSlugs !== [] || $plans !== [] || $businessTypes !== [];

        if ($tenant === null) {
            return ! $hasTargets;
        }

        $tenantSlug = $this->normalize((string) $tenant->slug);
        if ($tenantSlug !== '' && in_array($tenantSlug, $tenantSlugs, true)) {
            return true;
        }

        $plan = $this->tenantPlan($tenant);
        if ($plan !== '' && in_array($plan, $plans, true)) {
            return true;
        }

        $businessType = $this->tenantBusinessType($tenant);
        if ($businessType !== '' && in_array($businessType, $businessTypes, true)) {
            return true;
        }

        return ! $hasTargets;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rolloutFor(string $key): ?array
    {
        $needle = $this->normalize($key);

        if ($needle === '') {
            return null;
        }

        foreach ((new GeneralSettings)->feature_rollouts as $rollout) {
            if (! is_array($rollout)) {
                continue;
            }

            if ($this->normalize((string) ($rollout['key'] ?? '')) === $needle) {
                return $rollout;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $item = $this->normalize((string) $value);

            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function tenantPlan(Tenant $tenant): string
    {
        $rawPlan = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');

        return match (true) {
            $rawPlan instanceof TenantPlan => $rawPlan->value,
            is_string($rawPlan) => $this->normalize($rawPlan),
            default => '',
        };
    }

    private function tenantBusinessType(Tenant $tenant): string
    {
        $rawBusinessType = $tenant->getRawOriginal('business_type') ?? $tenant->getAttribute('business_type');

        return match (true) {
            $rawBusinessType instanceof BusinessType => $rawBusinessType->value,
            is_string($rawBusinessType) => $this->normalize($rawBusinessType),
            default => '',
        };
    }
}
