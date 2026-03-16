<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use Tests\TestCase;

class ConfigCapabilityManagerTest extends TestCase
{
    public function test_retail_tenant_has_inventory_tracking(): void
    {
        /** @var CapabilityManagerInterface $manager */
        $manager = $this->app->make(CapabilityManagerInterface::class);

        $tenant = new Tenant();
        $tenant->business_type = BusinessType::RETAIL;

        $this->assertTrue($manager->has($tenant, Capability::INVENTORY_TRACKING));
        $this->assertTrue($tenant->hasCapability(Capability::INVENTORY_TRACKING));
    }

    public function test_hybrid_tenant_does_not_enable_kitchen_display_by_default(): void
    {
        /** @var CapabilityManagerInterface $manager */
        $manager = $this->app->make(CapabilityManagerInterface::class);

        $tenant = new Tenant();
        $tenant->business_type = BusinessType::HYBRID;

        $this->assertFalse($manager->has($tenant, Capability::KITCHEN_DISPLAY));
    }
}
