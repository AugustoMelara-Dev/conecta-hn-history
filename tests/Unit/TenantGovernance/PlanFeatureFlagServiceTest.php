<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Models\Tenant;
use Tests\TestCase;

class PlanFeatureFlagServiceTest extends TestCase
{
    public function test_determine_plan_uses_plan_type_directly(): void
    {
        $tenant = new Tenant;
        $tenant->plan_type = TenantPlan::PRO;
        $tenant->subscription_status = 'active';

        $service = app(PlanFeatureFlagService::class);

        $this->assertSame(PlanFeatureFlagService::PLAN_PRO, $service->determinePlan($tenant));
        $this->assertTrue($service->canUseCoupons($tenant));
    }

    public function test_determine_plan_maps_growth_plans_as_paid(): void
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes([
            'plan_type' => Tenant::PLAN_CRECIMIENTO,
            'subscription_status' => 'active',
        ], true);

        $service = app(PlanFeatureFlagService::class);

        $this->assertSame(PlanFeatureFlagService::PLAN_PRO, $service->determinePlan($tenant));
        $this->assertFalse($service->isSemilla($tenant));
    }
}
