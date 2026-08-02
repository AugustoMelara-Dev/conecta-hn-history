<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Application\Billing\Global\EntitlementServiceInterface;
use App\Application\Billing\Global\GlobalEntitlementService;
use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\BillingCapability;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Infrastructure\Billing\Global\ConfigPriceBookReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use Tests\TestCase;

final class EntitlementMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitlements_route_all_capability_gates_through_configured_plan_snapshots(): void
    {
        $tenant = Tenant::factory()->create();
        $market = new MarketContext('CA', 'en-CA', 'CAD', 'America/Toronto', 'tenant_market');

        config()->set('dovra_billing.price_books.ca', [
            'version' => 'research-v1',
            'currency' => 'CAD',
            'plans' => [
                'explore' => [
                    'monthly_minor' => 0,
                    'annual_minor' => 0,
                    'sandbox_only' => true,
                    'live_entitled' => false,
                    'included_credits' => 0.0,
                    'capabilities' => ['ai.sandbox_agent'],
                ],
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent', 'billing.portal'],
                ],
                'grow' => [
                    'monthly_minor' => 14900,
                    'annual_minor' => 148404,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 100.0,
                    'capabilities' => ['ai.live_agent', 'channels.multiple'],
                ],
                'scale' => [
                    'monthly_minor' => 39900,
                    'annual_minor' => 397404,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 300.0,
                    'capabilities' => ['ai.live_agent', 'voice', 'api'],
                ],
                'enterprise' => [
                    'monthly_minor' => 150000,
                    'annual_minor' => 1494000,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 1000.0,
                    'capabilities' => ['ai.live_agent', 'voice', 'api', 'support.priority'],
                ],
            ],
        ]);

        self::assertTrue(interface_exists(EntitlementServiceInterface::class));
        self::assertTrue(class_exists(GlobalEntitlementService::class));

        $service = new GlobalEntitlementService(new ConfigPriceBookReader);

        $explore = GlobalTenantContext::fromArray([
            'tenant_id' => (string) $tenant->getKey(),
            'market' => $market,
            'plan' => 'explore',
            'status' => 'active',
        ]);
        $launch = $explore->withPlan('launch');
        $scale = $explore->withPlan('scale');
        $enterprise = $explore->withPlan('enterprise');

        self::assertTrue($service->allows($explore, BillingCapability::sandboxAgent())->allowed());
        self::assertFalse($service->allows($explore, BillingCapability::liveAgent())->allowed());
        self::assertSame('sandbox_only', $service->allows($explore, BillingCapability::liveAgent())->reason());

        self::assertTrue($service->allows($launch, BillingCapability::liveAgent())->allowed());
        self::assertTrue($service->allows($launch, BillingCapability::billingPortal())->allowed());
        self::assertTrue($service->allows($scale, BillingCapability::voice())->allowed());
        self::assertTrue($service->allows($scale, BillingCapability::api())->allowed());
        self::assertTrue($service->allows($enterprise, BillingCapability::prioritySupport())->allowed());
    }
}
