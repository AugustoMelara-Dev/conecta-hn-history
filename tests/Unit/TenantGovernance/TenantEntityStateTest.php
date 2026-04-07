<?php

declare(strict_types=1);

namespace Tests\Unit\TenantGovernance;

use App\Domain\TenantGovernance\Entities\TenantEntity;
use Tests\TestCase;

class TenantEntityStateTest extends TestCase
{
    private const LEGACY_PAID_PLAN_FLAG = 'is_pro';

    public function test_state_array_no_longer_serializes_legacy_paid_plan_flag(): void
    {
        $tenant = TenantEntity::create(
            id: '00000000-0000-0000-0000-000000000001',
            name: 'Tenant State Test',
            domain: 'tenant-state-test',
        );

        $tenant->upgradeToPlan('pro');

        $state = $tenant->toStateArray();

        $this->assertSame('pro', $state['plan_id']);
        $this->assertArrayNotHasKey(self::LEGACY_PAID_PLAN_FLAG, $state);
    }

    public function test_rehydration_requires_plan_id_once_legacy_fallback_is_removed(): void
    {
        $tenant = TenantEntity::fromStateArray([
            'id' => '00000000-0000-0000-0000-000000000002',
            'name' => 'Legacy Snapshot',
            'domain' => 'legacy-snapshot',
            'is_active' => true,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            self::LEGACY_PAID_PLAN_FLAG => true,
            'subscription_status' => 'active',
            'business_type' => 'retail',
            'version' => 1,
        ]);

        $this->assertNull($tenant->planId());
        $this->assertFalse($tenant->isPro());
    }
}
