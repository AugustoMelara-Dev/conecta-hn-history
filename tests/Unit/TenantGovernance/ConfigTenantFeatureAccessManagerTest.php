<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Application\TenantGovernance\Contracts\TenantFeatureAccessManagerInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Tenant;
use Tests\TestCase;

class ConfigTenantFeatureAccessManagerTest extends TestCase
{
    public function test_free_retail_tenant_cannot_access_pro_marketing_features(): void
    {
        /** @var TenantFeatureAccessManagerInterface $manager */
        $manager = $this->app->make(TenantFeatureAccessManagerInterface::class);

        $tenant = new Tenant;
        $tenant->business_type = BusinessType::RETAIL;
        $tenant->plan_type = TenantPlan::SEMILLA;
        $tenant->subscription_status = 'active';

        $this->assertFalse($manager->has($tenant, TenantFeature::MARKETING_CAMPAIGNS));
        $this->assertFalse($tenant->hasFeature(TenantFeature::ADVANCED_CATALOG));
        $this->assertTrue($tenant->hasFeature(TenantFeature::UPGRADE_PROMPT));
    }

    public function test_pro_service_tenant_can_access_loyalty_and_marketing_features(): void
    {
        /** @var TenantFeatureAccessManagerInterface $manager */
        $manager = $this->app->make(TenantFeatureAccessManagerInterface::class);

        $tenant = new Tenant;
        $tenant->business_type = BusinessType::SERVICE;
        $tenant->plan_type = TenantPlan::PRO;
        $tenant->subscription_status = 'active';

        $this->assertTrue($manager->has($tenant, TenantFeature::MARKETING_SUITE));
        $this->assertTrue($manager->has($tenant, TenantFeature::LOYALTY_PROGRAM));
        $this->assertTrue($tenant->hasFeature(TenantFeature::ADVANCED_BRANDING));
    }

    public function test_plan_resolution_uses_plan_type_directly(): void
    {
        /** @var TenantFeatureAccessManagerInterface $manager */
        $manager = $this->app->make(TenantFeatureAccessManagerInterface::class);

        $tenant = new Tenant;
        $tenant->business_type = BusinessType::SERVICE;
        $tenant->plan_type = TenantPlan::PRO;
        $tenant->subscription_status = 'active';

        $this->assertTrue($manager->has($tenant, TenantFeature::MARKETING_SUITE));
        $this->assertFalse($tenant->hasFeature(TenantFeature::UPGRADE_PROMPT));
    }
}
