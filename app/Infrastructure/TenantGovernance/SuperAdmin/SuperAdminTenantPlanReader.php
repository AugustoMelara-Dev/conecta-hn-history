<?php

declare(strict_types=1);

namespace App\Infrastructure\TenantGovernance\SuperAdmin;

use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

final class SuperAdminTenantPlanReader
{
    public function planKey(Tenant $tenant): string
    {
        $rawPlanKey = $tenant->getRawOriginal('plan_type') ?? $tenant->getAttribute('plan_type');
        $planKey = match (true) {
            $rawPlanKey instanceof TenantPlan => $rawPlanKey->value,
            is_string($rawPlanKey) => $rawPlanKey,
            default => '',
        };

        if ($planKey !== '') {
            return $planKey;
        }

        return $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT) ? Tenant::PLAN_SEMILLA : 'pro';
    }

    public function isPaidPlan(Tenant $tenant): bool
    {
        return ! $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT);
    }

    public function isBasePlan(Tenant $tenant): bool
    {
        return ! $this->isPaidPlan($tenant);
    }

    public function planLabel(string $planKey): string
    {
        return match ($planKey) {
            'pro' => 'PRO',
            Tenant::PLAN_CRECIMIENTO => 'Crecimiento',
            Tenant::PLAN_COSECHA => 'Cosecha',
            default => 'Semilla',
        };
    }

    public function isRenewalWindow(Tenant $tenant, int $days = 7): bool
    {
        return $this->isPaidPlan($tenant)
            && $tenant->subscription_ends_at instanceof Carbon
            && $tenant->subscription_ends_at->lte(now()->addDays($days));
    }
}
