<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Application\Billing\Global\GlobalEntitlementService;
use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\BillingCapability;
use App\Domain\Billing\Global\GlobalTenantContext;
use App\Infrastructure\Billing\Global\ConfigPriceBookReader;
use App\Infrastructure\Billing\Global\DeterministicBillingProviderFake;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WebhookLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_webhooks_drive_dunning_grace_and_suspension_without_accepting_replays(): void
    {
        $tenant = Tenant::factory()->create();
        $market = new MarketContext('CA', 'en-CA', 'CAD', 'America/Toronto', 'tenant_market');

        config()->set('dovra_billing.price_books.ca', [
            'version' => 'research-v1',
            'currency' => 'CAD',
            'plans' => [
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent', 'billing.portal'],
                ],
            ],
        ]);
        config()->set('dovra_billing.market_providers.ca', [
            'provider' => 'deterministic-fake',
            'checkout_mode' => 'external',
            'signing_secret' => 'ca-secret',
        ]);

        self::assertTrue(class_exists(DeterministicBillingProviderFake::class));

        $service = new GlobalEntitlementService(new ConfigPriceBookReader);
        $provider = new DeterministicBillingProviderFake($service);

        $tenantContext = GlobalTenantContext::fromArray([
            'tenant_id' => (string) $tenant->getKey(),
            'market' => $market,
            'plan' => 'launch',
            'status' => 'active',
        ]);

        $active = $provider->signedWebhook('ca', [
            'event_id' => 'evt-activate-1',
            'event_type' => 'subscription.activated',
            'tenant_id' => (string) $tenant->getKey(),
            'plan' => 'launch',
            'status' => 'active',
            'occurred_at' => now()->toIso8601String(),
        ]);
        $provider->handleWebhook('ca', $active['payload'], $active['signature']);
        self::assertTrue($service->allows($tenantContext, BillingCapability::liveAgent())->allowed());

        $grace = $provider->signedWebhook('ca', [
            'event_id' => 'evt-failed-1',
            'event_type' => 'invoice.payment_failed',
            'tenant_id' => (string) $tenant->getKey(),
            'plan' => 'launch',
            'status' => 'grace',
            'grace_ends_at' => now()->addDays(7)->toIso8601String(),
            'occurred_at' => now()->subSecond()->toIso8601String(),
        ]);
        $provider->handleWebhook('ca', $grace['payload'], $grace['signature']);
        self::assertTrue($service->allows($tenantContext, BillingCapability::liveAgent())->allowed());
        self::assertSame('grace', $service->allows($tenantContext, BillingCapability::liveAgent())->status());

        $provider->handleWebhook('ca', $grace['payload'], $grace['signature']);
        self::assertSame(1, DB::table('global_billing_lifecycle_events')->where('provider_event_id', 'evt-failed-1')->count());

        $suspend = $provider->signedWebhook('ca', [
            'event_id' => 'evt-suspend-1',
            'event_type' => 'subscription.suspended',
            'tenant_id' => (string) $tenant->getKey(),
            'plan' => 'launch',
            'status' => 'suspended',
            'occurred_at' => now()->toIso8601String(),
        ]);
        $provider->handleWebhook('ca', $suspend['payload'], $suspend['signature']);
        self::assertFalse($service->allows($tenantContext, BillingCapability::liveAgent())->allowed());
        self::assertSame('suspended', $service->allows($tenantContext, BillingCapability::liveAgent())->status());
    }

    public function test_invalid_signatures_are_rejected(): void
    {
        $tenant = Tenant::factory()->create();

        config()->set('dovra_billing.price_books.ca', [
            'version' => 'research-v1',
            'currency' => 'CAD',
            'plans' => [
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent', 'billing.portal'],
                ],
            ],
        ]);
        config()->set('dovra_billing.market_providers.ca', [
            'provider' => 'deterministic-fake',
            'checkout_mode' => 'external',
            'signing_secret' => 'ca-secret',
        ]);

        self::assertTrue(class_exists(DeterministicBillingProviderFake::class));

        $service = new GlobalEntitlementService(new ConfigPriceBookReader);
        $provider = new DeterministicBillingProviderFake($service);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('signature');

        $provider->handleWebhook('ca', json_encode([
            'event_id' => 'evt-invalid-1',
            'event_type' => 'subscription.activated',
            'tenant_id' => (string) $tenant->getKey(),
            'plan' => 'launch',
            'status' => 'active',
        ], JSON_THROW_ON_ERROR), 'bad-signature');
    }

    public function test_current_state_ignores_same_tenant_entitlements_from_other_market(): void
    {
        $tenant = Tenant::factory()->create();
        $occurredAt = now()->subMinute();

        config()->set('dovra_billing.price_books.ca', [
            'version' => 'research-v1',
            'currency' => 'CAD',
            'plans' => [
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent'],
                ],
            ],
        ]);

        DB::table('global_billing_entitlements')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => (string) $tenant->getKey(),
            'market_key' => 'hn',
            'price_book_version' => 'hn-v1',
            'plan_key' => 'launch',
            'currency_code' => 'HNL',
            'status' => 'suspended',
            'sandbox_access' => false,
            'live_access' => false,
            'included_credits' => 0,
            'grace_ends_at' => null,
            'provider_key' => 'deterministic-fake',
            'provider_event_id' => 'evt-other-market-1',
            'snapshot' => json_encode([
                'tenant_id' => (string) $tenant->getKey(),
                'plan' => 'launch',
                'status' => 'suspended',
                'occurred_at' => $occurredAt->toIso8601String(),
            ], JSON_THROW_ON_ERROR),
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        $context = GlobalTenantContext::fromArray([
            'tenant_id' => (string) $tenant->getKey(),
            'market' => new MarketContext('CA', 'en-CA', 'CAD', 'America/Toronto', 'tenant_market'),
            'plan' => 'launch',
            'status' => 'active',
        ]);

        $service = new GlobalEntitlementService(new ConfigPriceBookReader);

        self::assertTrue($service->allows($context, BillingCapability::liveAgent())->allowed());
    }

    public function test_provider_fake_reads_the_config_secret_without_persisting_secret_material(): void
    {
        config()->set('dovra_billing.price_books.ca', [
            'version' => 'ca-2026-08-v1',
            'currency' => 'CAD',
            'plans' => [
                'launch' => [
                    'monthly_minor' => 2900,
                    'annual_minor' => 29000,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 100,
                    'capabilities' => ['private_agent'],
                ],
            ],
        ]);
        config()->set('dovra_billing.market_providers.ca', [
            'provider' => 'deterministic-ca',
            'checkout_mode' => 'external',
            'signing_secret' => 'config-only-secret',
        ]);

        $tenant = Tenant::factory()->create();
        $service = new GlobalEntitlementService(new ConfigPriceBookReader);
        $provider = new DeterministicBillingProviderFake($service);
        $signed = $provider->signedWebhook('ca', [
            'event_id' => 'evt-secret-1',
            'event_type' => 'subscription.activated',
            'tenant_id' => (string) $tenant->getKey(),
            'plan' => 'launch',
            'status' => 'active',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $provider->handleWebhook('ca', $signed['payload'], $signed['signature']);

        self::assertNull(DB::table('global_billing_market_providers')
            ->where('market_key', 'ca')
            ->value('signing_secret'));
        self::assertSame(1, DB::table('global_billing_lifecycle_events')
            ->where('provider_event_id', 'evt-secret-1')
            ->count());
    }

    public function test_live_checkout_contract_remains_external(): void
    {
        $tenant = Tenant::factory()->create();
        $market = new MarketContext('CA', 'en-CA', 'CAD', 'America/Toronto', 'tenant_market');

        config()->set('dovra_billing.price_books.ca', [
            'version' => 'research-v1',
            'currency' => 'CAD',
            'plans' => [
                'launch' => [
                    'monthly_minor' => 4900,
                    'annual_minor' => 48804,
                    'sandbox_only' => false,
                    'live_entitled' => true,
                    'included_credits' => 25.0,
                    'capabilities' => ['ai.live_agent', 'billing.portal'],
                ],
            ],
        ]);
        config()->set('dovra_billing.market_providers.ca', [
            'provider' => 'deterministic-fake',
            'checkout_mode' => 'external',
            'signing_secret' => 'ca-secret',
        ]);

        self::assertTrue(class_exists(DeterministicBillingProviderFake::class));

        $service = new GlobalEntitlementService(new ConfigPriceBookReader);
        $provider = new DeterministicBillingProviderFake($service);

        $checkout = $provider->checkoutContract(GlobalTenantContext::fromArray([
            'tenant_id' => (string) $tenant->getKey(),
            'market' => $market,
            'plan' => 'launch',
            'status' => 'active',
        ]));

        self::assertSame('external', $checkout->mode());
    }
}
