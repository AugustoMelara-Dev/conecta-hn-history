<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Crm\CrmCapabilityMap;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\CustomerTouchpoint;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Lead;
use PHPUnit\Framework\TestCase;

final class CrmCapabilityMapTest extends TestCase
{
    public function test_it_declares_the_current_canonical_crm_boundaries(): void
    {
        $map = new CrmCapabilityMap;
        $capabilities = $map->capabilities();

        self::assertSame(CustomerProfile::class, $capabilities[CrmCapabilityMap::CONTACTS]['canonical_model']);
        self::assertSame(Lead::class, $capabilities[CrmCapabilityMap::LEADS]['canonical_model']);
        self::assertSame('supported', $capabilities[CrmCapabilityMap::CONTACTS]['status']);
        self::assertSame('supported', $capabilities[CrmCapabilityMap::LEADS]['status']);
        self::assertSame('partial', $capabilities[CrmCapabilityMap::ACTIVITIES]['status']);

        self::assertSame(CrmDeal::class, $capabilities[CrmCapabilityMap::DEALS]['canonical_model']);
        self::assertSame(CrmPipeline::class, $capabilities[CrmCapabilityMap::PIPELINES]['canonical_model']);
        self::assertSame(CrmStage::class, $capabilities[CrmCapabilityMap::STAGES]['canonical_model']);
        self::assertSame('gated', $capabilities[CrmCapabilityMap::DEALS]['status']);
        self::assertSame('gated', $capabilities[CrmCapabilityMap::PIPELINES]['status']);
        self::assertSame('gated', $capabilities[CrmCapabilityMap::STAGES]['status']);
        self::assertSame('gated_partial', $capabilities[CrmCapabilityMap::CRM_API]['status']);
    }

    public function test_it_exposes_activity_sources_without_treating_them_as_deals(): void
    {
        $map = new CrmCapabilityMap;

        self::assertSame([
            CustomerTouchpoint::class,
            CustomerNote::class,
            CustomerTask::class,
        ], $map->activityModels());

        self::assertArrayNotHasKey(CrmCapabilityMap::DEALS, $map->missingCapabilities());
    }
}
