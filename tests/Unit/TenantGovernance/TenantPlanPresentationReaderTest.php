<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Enums\BusinessType;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Product;
use App\Models\Tenant;
use Tests\TestCase;

class TenantPlanPresentationReaderTest extends TestCase
{
    public function test_base_tenant_receives_upgrade_narrative_and_configured_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'business_type' => BusinessType::RETAIL->value,
            'plan_type' => TenantPlan::SEMILLA->value,
            'product_limit' => 7,
        ]);

        Product::factory()->forTenant($tenant)->count(3)->create();

        $reader = $this->app->make(TenantPlanPresentationReader::class);
        $presentation = $reader->forTenant($tenant->fresh());

        $this->assertSame('Base', $presentation['label']);
        $this->assertTrue($presentation['isUpgradeable']);
        $this->assertFalse($presentation['isCatalogUnlimited']);
        $this->assertSame(7, $presentation['productLimit']);
        $this->assertSame(4, $presentation['productSlotsRemaining']);
        $this->assertStringContainsString('catalogo', $presentation['catalogStatusCopy']);
    }

    public function test_pro_tenant_receives_unlimited_catalog_and_full_audience_copy(): void
    {
        $tenant = Tenant::factory()->pro()->create([
            'business_type' => BusinessType::RETAIL->value,
        ]);

        $reader = $this->app->make(TenantPlanPresentationReader::class);
        $presentation = $reader->forTenant($tenant);

        $this->assertSame('PRO', $presentation['label']);
        $this->assertFalse($presentation['isUpgradeable']);
        $this->assertTrue($presentation['isCatalogUnlimited']);
        $this->assertNull($presentation['productLimit']);
        $this->assertTrue($presentation['hasFollowerInsights']);
        $this->assertStringContainsString('detalle completo', $presentation['followersCopy']);
    }
}
