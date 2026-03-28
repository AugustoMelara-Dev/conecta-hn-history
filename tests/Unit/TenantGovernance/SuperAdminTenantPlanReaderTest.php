<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Infrastructure\TenantGovernance\SuperAdmin\SuperAdminTenantPlanReader;
use App\Models\Tenant;
use Tests\TestCase;

class SuperAdminTenantPlanReaderTest extends TestCase
{
    public function test_base_tenant_is_classified_from_feature_matrix(): void
    {
        $tenant = new Tenant;
        $tenant->business_type = BusinessType::RETAIL;
        $tenant->plan_type = TenantPlan::SEMILLA;
        $tenant->subscription_status = 'active';

        $reader = $this->app->make(SuperAdminTenantPlanReader::class);

        $this->assertSame(Tenant::PLAN_SEMILLA, $reader->planKey($tenant));
        $this->assertTrue($reader->isBasePlan($tenant));
        $this->assertFalse($reader->isPaidPlan($tenant));
    }

    public function test_paid_tenant_detects_renewal_window_without_legacy_paid_plan_flag(): void
    {
        $tenant = new Tenant;
        $tenant->business_type = BusinessType::RETAIL;
        $tenant->plan_type = TenantPlan::PRO;
        $tenant->subscription_status = 'active';
        $tenant->subscription_ends_at = now()->addDays(3);

        $reader = $this->app->make(SuperAdminTenantPlanReader::class);

        $this->assertSame('pro', $reader->planKey($tenant));
        $this->assertTrue($reader->isPaidPlan($tenant));
        $this->assertTrue($reader->isRenewalWindow($tenant));
        $this->assertSame('PRO', $reader->planLabel('pro'));
    }
}
