<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Application\Markets\HondurasMarketBackfill;
use App\Application\Markets\MissingMarketContextException;
use App\Infrastructure\AI\EloquentTenantAgentReader;
use App\Infrastructure\Tenant\TenantPrivateAgentService;
use App\Models\Appointment;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class GlobalPrivateAgentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_private_agent_uses_each_tenants_local_day_currency_and_timezone(): void
    {
        CarbonImmutable::setTestNow('2026-01-15 05:30:00 UTC');

        $canadianOwner = User::factory()->create();
        $canadian = Tenant::factory()->pro()->forUser($canadianOwner)->create(['is_active' => true]);
        $canadianOwner->forceFill(['tenant_id' => $canadian->id])->save();
        $honduranOwner = User::factory()->create();
        $honduran = Tenant::factory()->pro()->forUser($honduranOwner)->create(['is_active' => true]);
        $honduranOwner->forceFill(['tenant_id' => $honduran->id])->save();

        app(HondurasMarketBackfill::class)->run();
        GlobalMarketFixture::assignCanada($canadian);

        $this->orderAt($canadian, '2026-01-15 05:15:00 UTC');
        $this->orderAt($canadian, '2026-01-15 04:45:00 UTC');
        $this->orderAt($honduran, '2026-01-15 05:15:00 UTC');
        $this->orderAt($honduran, '2026-01-15 06:15:00 UTC');

        $this->verifiedOutcome($canadian, 'ca-current', 12_345, 'CAD', '2026-01-15 05:15:00 UTC');
        $this->verifiedOutcome($canadian, 'ca-previous', 98_765, 'CAD', '2026-01-15 04:45:00 UTC');
        $this->verifiedOutcome($canadian, 'ca-wrong-currency', 77_700, 'HNL', '2026-01-15 05:20:00 UTC');
        $this->verifiedOutcome($honduran, 'hn-current', 5_000, 'HNL', '2026-01-15 05:15:00 UTC');
        $this->verifiedOutcome($honduran, 'hn-next', 99_900, 'HNL', '2026-01-15 06:15:00 UTC');

        $this->appointmentAt($canadian, '2026-01-15 05:20:00 UTC', '2026-01-15 06:20:00 UTC');
        $this->appointmentAt($canadian, '2026-01-15 04:45:00 UTC', '2026-01-15 05:00:00 UTC');
        $this->appointmentAt($honduran, '2026-01-15 05:20:00 UTC', '2026-01-15 05:50:00 UTC');
        $this->appointmentAt($honduran, '2026-01-15 06:15:00 UTC', '2026-01-15 06:45:00 UTC');

        config([
            'ai.private.enabled' => true,
            'ai.private.tenant_allowlist' => [(string) $canadian->id, (string) $honduran->id],
        ]);

        Sanctum::actingAs($canadianOwner);
        $canadianSales = $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'What did I sell today?',
        ])->assertOk()
            ->assertJsonPath('data.currency', 'CAD')
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.verified_net_revenue', 123.45)
            ->assertJsonPath('meta.country', 'CA')
            ->assertJsonPath('meta.locale', 'en-CA')
            ->assertJsonPath('meta.currency', 'CAD')
            ->assertJsonPath('meta.timezone', 'America/Toronto');
        self::assertStringContainsString('CAD 123.45', (string) $canadianSales->json('answer'));
        self::assertStringNotContainsString('L.', (string) $canadianSales->json('answer'));

        $canadianAppointments = $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'What appointments are on my agenda today?',
        ])->assertOk()
            ->assertJsonPath('data.appointments_count', 1)
            ->assertJsonPath('data.appointments.0.scheduled_at', '2026-01-15T00:20:00-05:00');

        Sanctum::actingAs($honduranOwner);
        $honduranSales = $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'cuanto vendi hoy',
        ])->assertOk()
            ->assertJsonPath('data.currency', 'HNL')
            ->assertJsonPath('data.orders_count', 1)
            ->assertJsonPath('data.verified_net_revenue', 50)
            ->assertJsonPath('meta.country', 'HN')
            ->assertJsonPath('meta.locale', 'es-HN')
            ->assertJsonPath('meta.currency', 'HNL')
            ->assertJsonPath('meta.timezone', 'America/Tegucigalpa');
        self::assertStringContainsString('HNL 50.00', (string) $honduranSales->json('answer'));
        self::assertStringNotContainsString('CAD', (string) $honduranSales->getContent());

        $this->postJson('/api/v1/tenant/agent/chat', [
            'message' => 'que citas tengo hoy',
        ])->assertOk()
            ->assertJsonPath('data.appointments_count', 1)
            ->assertJsonPath('data.appointments.0.scheduled_at', '2026-01-14T23:20:00-06:00');
    }

    public function test_legacy_private_service_is_a_compatible_adapter_over_the_canonical_reader(): void
    {
        CarbonImmutable::setTestNow('2026-01-15 05:30:00 UTC');
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create(['is_active' => true]);
        app(HondurasMarketBackfill::class)->run();
        GlobalMarketFixture::assignCanada($tenant);
        $this->verifiedOutcome($tenant, 'adapter', 2_500, 'CAD', '2026-01-15 05:15:00 UTC');

        $canonical = app(EloquentTenantAgentReader::class)->answer((string) $tenant->id, 'cuanto vendi hoy');
        $compatible = app(TenantPrivateAgentService::class)->answer($tenant, 'cuanto vendi hoy');

        self::assertSame($canonical, $compatible);
    }

    public function test_catalog_product_and_service_prices_use_the_market_presenter(): void
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($tenant);
        $product = Product::factory()->forTenant($tenant)->create([
            'price' => 12.34,
            'price_minor' => 1_234,
            'currency' => 'CAD',
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $tenant->id,
            'price' => 56.78,
            'price_minor' => 5_678,
            'currency' => 'CAD',
            'is_active' => true,
        ]);
        $this->app->instance(KnowledgeRetriever::class, new class($product, $service) implements KnowledgeRetriever
        {
            public function __construct(private Product $product, private Service $service) {}

            public function searchPublic(string $query, int $limit = 12): array
            {
                return [];
            }

            public function searchTenant(string $query, string $tenantId, int $limit = 12): array
            {
                return [[
                    'resource_type' => 'product',
                    'resource_id' => (string) $this->product->id,
                ], [
                    'resource_type' => 'service',
                    'resource_id' => (string) $this->service->id,
                ]];
            }
        });

        $response = app(EloquentTenantAgentReader::class)->answer((string) $tenant->id, 'show catalog');

        self::assertSame('CAD', $response['data']['currency']);
        self::assertSame(['CAD 12.34', 'CAD 56.78'], array_column($response['data']['items'], 'price_label'));
    }

    public function test_canonical_reader_fails_closed_without_market_evidence(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        DB::table('tenant_markets')->where('tenant_id', $tenant->id)->delete();

        $this->expectException(MissingMarketContextException::class);

        app(EloquentTenantAgentReader::class)->answer((string) $tenant->id, 'cuanto vendi hoy');
    }

    public function test_reader_resolves_exact_tenant_despite_a_different_selected_public_city(): void
    {
        $tenantCity = City::factory()->create();
        $selectedCity = City::factory()->create();
        $tenant = Tenant::factory()->create(['city_id' => $tenantCity->id]);
        GlobalMarketFixture::assignCanada($tenant);
        $originalRequest = $this->app->make('request');
        $consoleProperty = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $originalConsoleState = $consoleProperty->getValue($this->app);
        $session = $this->app->make('session')->driver();
        $session->start();
        $session->put('user_selected_city_id', $selectedCity->id);
        $request = Request::create('/api/v1/tenant/agent/chat', 'POST');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $consoleProperty->setValue($this->app, false);

        try {
            $response = app(EloquentTenantAgentReader::class)->answer((string) $tenant->id, 'cuanto vendi hoy');

            self::assertSame((string) $tenant->id, $response['tenant_id']);
            self::assertSame('CAD', $response['meta']['currency']);
        } finally {
            $consoleProperty->setValue($this->app, $originalConsoleState);
            $this->app->instance('request', $originalRequest);
        }
    }

    private function orderAt(Tenant $tenant, string $createdAt): void
    {
        $order = Order::factory()->forTenant($tenant)->create();
        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => CarbonImmutable::parse($createdAt),
            'updated_at' => CarbonImmutable::parse($createdAt),
        ]);
    }

    private function appointmentAt(Tenant $tenant, string $start, string $end): void
    {
        Appointment::withoutEvents(fn (): Appointment => Appointment::factory()->create([
            'tenant_id' => $tenant->id,
            'scheduled_at' => CarbonImmutable::parse($start),
            'end_at' => CarbonImmutable::parse($end),
        ]));
    }

    private function verifiedOutcome(
        Tenant $tenant,
        string $aggregateId,
        int $amountMinor,
        string $currency,
        string $occurredAt,
    ): void {
        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => (string) $tenant->id,
            'aggregate_type' => 'order',
            'aggregate_id' => $aggregateId,
            'outcome' => 'order_paid',
            'projection_key' => "{$tenant->id}:{$aggregateId}:global-private-agent",
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'source_occurred_at' => CarbonImmutable::parse($occurredAt),
            'source_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
