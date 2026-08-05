<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Application\Markets\TenantMarketAssignmentService;
use App\Application\Tenant\Transformers\TenantMicrositeTransformer;
use App\Domain\Analytics\Enums\CommercialOutcome;
use App\Domain\TenantGovernance\Enums\CommercialMode;
use App\Infrastructure\Sales\EloquentReportReader;
use App\Infrastructure\Tenant\EloquentDashboardMetricsRepository;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GlobalMarketRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'maintenance_mode' => false,
            'allow_registrations' => true,
            'free_plan_product_limit' => 10,
            'pro_plan_product_limit' => 100,
            'pro_plan_price' => 500.0,
        ] as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'general', 'name' => $name],
                [
                    'locked' => false,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function test_a_non_honduras_business_registers_with_explicit_market_context(): void
    {
        $localityId = $this->seedCanadianMarket();
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Professional services',
            'slug' => 'professional-services-global',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->withSession(['_token' => 'test-token'])
            ->post(route('tenant.register.store'), [
                '_token' => 'test-token',
                'business_name' => 'Toronto Consulting',
                'slug' => 'toronto-consulting',
                'name' => 'Global Owner',
                'email' => 'global.owner@example.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'country_code' => 'CA',
                'locality_id' => $localityId,
                'locale_code' => 'en-CA',
                'currency_code' => 'CAD',
                'timezone' => 'America/Toronto',
                'address' => '100 King Street West',
                'category_id' => $categoryId,
                'commercial_mode' => CommercialMode::CONSULTING_LEADS->value,
                'selected_plan' => 'free',
                'terms_accepted' => true,
                'privacy_accepted' => true,
                'terms_version' => '2026-05-10',
                'privacy_version' => '2026-05-10',
            ]);

        $response->assertRedirect('/app/toronto-consulting');

        $tenant = Tenant::query()->where('slug', 'toronto-consulting')->firstOrFail();
        self::assertNull($tenant->city_id);
        $this->assertDatabaseHas('tenant_markets', [
            'tenant_id' => $tenant->id,
            'country_code' => 'CA',
            'locale_code' => 'en-CA',
            'currency_code' => 'CAD',
            'timezone_name' => 'America/Toronto',
        ]);
        $this->assertDatabaseHas('locations', [
            'tenant_id' => $tenant->id,
            'locality_id' => $localityId,
            'country_code' => 'CA',
            'address' => '100 King Street West',
        ]);

        $publicProfile = app(TenantMicrositeTransformer::class)->transform($tenant->refresh());
        self::assertSame('CA', $publicProfile['market']['country_code']);
        self::assertSame('en-CA', $publicProfile['market']['locale']);
        self::assertSame('CAD', $publicProfile['market']['currency']);
        self::assertSame('America/Toronto', $publicProfile['market']['timezone']);
        self::assertSame('Toronto', $publicProfile['location']['locality']);
        self::assertSame('Ontario', $publicProfile['location']['administrative_area']);
        self::assertSame('100 King Street West', $publicProfile['location']['address']);
        self::assertStringNotContainsString('Honduras', json_encode($publicProfile, JSON_THROW_ON_ERROR));
    }

    public function test_a_new_order_records_the_tenant_market_currency_instead_of_a_global_default(): void
    {
        $localityId = $this->seedCanadianMarket();
        $tenant = Tenant::factory()->create(['city_id' => null]);
        app(TenantMarketAssignmentService::class)->assign(
            tenantId: (string) $tenant->id,
            countryCode: 'CA',
            localeCode: 'en-CA',
            currencyCode: 'CAD',
            timezone: 'America/Toronto',
            localityId: $localityId,
            locationName: 'Toronto store',
            address: null,
        );
        $product = Product::factory()
            ->forTenant($tenant)
            ->withStock(10)
            ->create(['price' => 25, 'is_active' => true, 'is_visible' => true]);

        $response = $this->withHeader('Idempotency-Key', 'global-market-order-001')
            ->postJson('/api/v1/orders', [
                'customer_name' => 'Canadian buyer',
                'customer_phone' => '+1 416 555 0100',
                'delivery_type' => 'pickup',
                'payment_method' => 'cash',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'options' => [],
                ]],
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('orders', [
            'tenant_id' => $tenant->id,
            'total' => 50,
            'currency' => 'CAD',
        ]);

        $order = DB::table('orders')->where('tenant_id', $tenant->id)->first();
        $mismatch = app(WebhookService::class)->handlePaymentSucceeded([
            'order_number' => $order->order_number,
            'transaction_id' => 'ca-payment-wrong-currency',
            'amount' => 50,
            'currency' => 'USD',
        ]);
        self::assertSame(['status' => 'error', 'reason' => 'currency_mismatch'], $mismatch);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'currency' => 'CAD',
            'payment_status' => 'pending',
        ]);

        $accepted = app(WebhookService::class)->handlePaymentSucceeded([
            'order_number' => $order->order_number,
            'transaction_id' => 'ca-payment-recorded-currency',
            'amount' => 50,
        ]);
        self::assertSame('success', $accepted['status']);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'currency' => 'CAD',
            'payment_status' => 'paid',
        ]);

        DB::table('verified_commercial_outcomes')->insert([
            'tenant_id' => $tenant->id,
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $order->id,
            'outcome' => CommercialOutcome::OrderPaid->value,
            'projection_key' => "{$tenant->id}:global-market:historical-hnl",
            'amount_minor' => 900000,
            'currency' => 'HNL',
            'source_occurred_at' => now(),
            'source_version' => 1,
            'metadata' => json_encode(['authority' => 'test'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(EloquentReportReader::class)->getSalesSummary(
            (string) $tenant->id,
            now()->subMinute(),
            now()->addMinute(),
        );
        $dashboard = app(EloquentDashboardMetricsRepository::class)->getKpiSummary((string) $tenant->id);
        self::assertSame('CAD', $report['currency']);
        self::assertSame(50.0, $report['total_revenue']);
        self::assertSame('CAD', $dashboard['currency']);
        self::assertSame(50.0, $dashboard['today_revenue']);
    }

    private function seedCanadianMarket(): int
    {
        $now = now();
        DB::table('countries')->insert([
            'code' => 'CA', 'name' => 'Canada', 'native_name' => 'Canada',
            'calling_code' => '+1', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('currencies')->insert([
            'code' => 'CAD', 'name' => 'Canadian dollar', 'symbol' => '$',
            'minor_unit' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('locales')->insert([
            'code' => 'en-CA', 'name' => 'English (Canada)', 'native_name' => 'English (Canada)',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('timezones')->insert([
            'name' => 'America/Toronto', 'label' => 'Eastern Time — Toronto',
            'utc_offset' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $packId = (string) Str::uuid();
        DB::table('market_packs')->insert([
            'id' => $packId, 'key' => 'ca', 'country_code' => 'CA',
            'name' => 'Canada', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('market_pack_versions')->insert([
            'id' => (string) Str::uuid(),
            'market_pack_id' => $packId,
            'version' => 1,
            'status' => 'published',
            'default_locale_code' => 'en-CA',
            'default_currency_code' => 'CAD',
            'default_timezone_name' => 'America/Toronto',
            'supported_locales' => json_encode(['en-CA'], JSON_THROW_ON_ERROR),
            'supported_currencies' => json_encode(['CAD'], JSON_THROW_ON_ERROR),
            'supported_timezones' => json_encode(['America/Toronto'], JSON_THROW_ON_ERROR),
            'capabilities' => json_encode([], JSON_THROW_ON_ERROR),
            'address_schema' => json_encode(['levels' => ['province', 'city']], JSON_THROW_ON_ERROR),
            'effective_from' => $now,
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $areaId = DB::table('administrative_areas')->insertGetId([
            'country_code' => 'CA', 'parent_id' => null, 'level' => 1, 'type' => 'province',
            'code' => 'ON', 'name' => 'Ontario', 'slug' => 'ontario',
            'legacy_department_id' => null, 'legacy_municipality_id' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return DB::table('localities')->insertGetId([
            'country_code' => 'CA', 'administrative_area_id' => $areaId,
            'name' => 'Toronto', 'slug' => 'toronto', 'timezone_name' => 'America/Toronto',
            'latitude' => 43.6532, 'longitude' => -79.3832, 'legacy_city_id' => null,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
