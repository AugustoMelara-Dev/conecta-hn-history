<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Domain\TenantGovernance\Enums\Capability;
use App\Infrastructure\Billing\Persistence\ConfigPlanRepository;
use App\Infrastructure\TenantGovernance\Capabilities\ConfigCapabilityManager;
use App\Models\Tenant;
use Tests\TestCase;

final class EnterprisePlanSafetyTest extends TestCase
{
    public function test_enterprise_is_not_exposed_as_a_free_self_service_plan_by_default(): void
    {
        $repository = new ConfigPlanRepository;

        self::assertNull($repository->findByIdentifier('enterprise'));
        self::assertNotContains('enterprise', array_map(
            static fn ($plan): string => $plan->id,
            $repository->getAllActive(),
        ));
    }

    public function test_enterprise_can_only_be_exposed_after_explicit_active_configuration(): void
    {
        config()->set('subscriptions.plans.enterprise', [
            'name' => 'Plan Empresa',
            'price' => 149900,
            'currency' => 'HNL',
            'stripe_price_id' => 'price_enterprise_test',
            'is_active' => true,
        ]);

        $plan = (new ConfigPlanRepository)->findByIdentifier('enterprise');

        self::assertNotNull($plan);
        self::assertSame('enterprise', $plan->id);
        self::assertSame(149900, $plan->price->amount);
    }

    public function test_existing_enterprise_tenant_receives_at_least_pro_capabilities(): void
    {
        $tenant = new Tenant;
        $tenant->forceFill([
            'id' => '550e8400-e29b-41d4-a716-446655440010',
            'plan_type' => 'enterprise',
            'commercial_mode' => 'products',
        ]);

        $capabilities = (new ConfigCapabilityManager)->allForTenant($tenant);

        self::assertContains(Capability::ANALYTICS_BASIC, $capabilities);
        self::assertContains(Capability::AI_CONTENT, $capabilities);
        self::assertContains(Capability::COUPONS_BASIC, $capabilities);
    }
}
