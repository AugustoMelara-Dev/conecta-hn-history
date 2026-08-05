<?php

declare(strict_types=1);

namespace Tests\Feature\Discovery;

use App\Application\Ai\Conversation\PublicConversationInput;
use App\Application\Discovery\PublishedCommercialProjectionService;
use App\Application\Discovery\PublishedDiscoveryGuideService;
use App\Application\Markets\MarketContext;
use App\Application\Markets\TenantMarketResolver;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class PublishedDiscoveryGuideIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_only_public_fields_from_the_current_projection(): void
    {
        [$platform, $merchant, $product] = $this->projectedFixture();
        $market = $this->market($platform);

        $response = app(PublishedDiscoveryGuideService::class)->answer(
            new PublicConversationInput(
                message: 'laptop',
                idempotencyKey: 'published-guide-public-shape',
                actorId: 'guest-1',
                budget: 150.00,
            ),
            $market,
        );

        self::assertCount(1, $response['items']);
        $item = $response['items'][0];
        self::assertSame('product:'.$product->id, $item['evidence_id']);
        self::assertSame('product', $item['source_type']);
        self::assertSame('Published laptop', $item['name']);
        self::assertSame('A public laptop offer.', $item['offer_description']);
        self::assertSame('/t/public-merchant', $item['business_url']);
        self::assertArrayNotHasKey('tenant_id', $item);
        self::assertArrayNotHasKey('source_tenant_id', $item);
        self::assertArrayNotHasKey('plan_type', $item);
        self::assertArrayNotHasKey('ranking_score', $item);
        self::assertArrayNotHasKey('freshness_score', $item);
        self::assertArrayNotHasKey('semantic_score', $item);

        $projectionId = DB::table('discovery_published_offers')
            ->where('projection_key', 'product:'.$product->id)
            ->value('id');
        $evidenceKeys = DB::table('discovery_offer_evidence')
            ->where('projection_id', $projectionId)
            ->pluck('evidence_key')
            ->all();
        self::assertContains('product:'.$product->id, $evidenceKeys);
        self::assertSame('CA', $response['meta']['market_country_code']);
        self::assertSame('CAD', $response['meta']['currency_code']);
        self::assertSame('published_commercial_projection', $response['meta']['retrieval_source']);
    }

    public function test_it_uses_currency_minor_unit_for_budget_conversion(): void
    {
        [$platform] = $this->projectedFixture('JP');
        $market = $this->market($platform);

        $response = app(PublishedDiscoveryGuideService::class)->answer(
            new PublicConversationInput(
                message: 'laptop',
                idempotencyKey: 'published-guide-jpy-budget',
                actorId: 'guest-1',
                budget: 1500.00,
            ),
            $market,
        );

        self::assertCount(1, $response['items']);
        self::assertSame(1500, $response['items'][0]['price']);
        self::assertSame('JPY', $response['meta']['currency_code']);
    }

    public function test_budget_fraction_below_a_jpy_minor_unit_never_widens_the_constraint(): void
    {
        [$platform, $merchant] = $this->projectedFixture('JP');
        Product::factory()->forTenant($merchant)->create([
            'name' => 'Published laptop above budget',
            'description' => 'A more expensive public laptop offer.',
            'price' => 1501.00,
            'price_minor' => 1501,
            'currency' => 'JPY',
            'is_active' => true,
            'is_visible' => true,
        ]);
        app(PublishedCommercialProjectionService::class)->projectCurrent();

        $response = app(PublishedDiscoveryGuideService::class)->answer(
            new PublicConversationInput(
                message: 'laptop',
                idempotencyKey: 'published-guide-jpy-boundary',
                actorId: 'guest-1',
                budget: 1500.6,
            ),
            $this->market($platform),
        );

        self::assertSame(['Published laptop'], array_column($response['items'], 'name'));
    }

    public function test_city_and_category_constraints_fail_closed_without_raw_fallback(): void
    {
        [$platform] = $this->projectedFixture();
        $market = $this->market($platform);

        foreach ([['city' => 'toronto'], ['category' => 'electronics']] as $index => $constraints) {
            $city = $constraints['city'] ?? null;
            $category = $constraints['category'] ?? null;
            $response = app(PublishedDiscoveryGuideService::class)->answer(
                new PublicConversationInput(
                    message: 'laptop',
                    idempotencyKey: 'published-guide-unsupported-'.$index,
                    actorId: 'guest-1',
                    city: $city,
                    category: $category,
                ),
                $market,
            );

            self::assertSame([], $response['items']);
            self::assertArrayNotHasKey('unsupported_constraints', $response['meta']);
            self::assertSame(0, $response['meta']['total_results']);
            self::assertSame('deterministic_public_projection', $response['meta']['mode']);
        }
    }

    public function test_unprojected_or_wrong_market_records_never_leak(): void
    {
        $platform = Tenant::factory()->create();
        GlobalMarketFixture::assignCanada($platform);
        $merchant = Tenant::factory()->create([
            'name' => 'Unprojected Merchant',
            'slug' => 'unprojected-merchant',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        GlobalMarketFixture::assignCanada($merchant);
        Product::factory()->forTenant($merchant)->create([
            'name' => 'Unprojected laptop',
            'description' => 'Must not be read from the raw catalog.',
            'price' => 100.00,
            'price_minor' => 10000,
            'currency' => 'CAD',
            'is_active' => true,
            'is_visible' => true,
        ]);

        $guide = app(PublishedDiscoveryGuideService::class);
        $market = $this->market($platform);
        $unprojected = $guide->answer(
            new PublicConversationInput('laptop', 'published-guide-unprojected', actorId: 'guest-1'),
            $market,
        );
        self::assertSame([], $unprojected['items']);

        app(PublishedCommercialProjectionService::class)->projectCurrent();
        $wrongMarket = new MarketContext('HN', 'es-HN', 'HNL', 'America/Tegucigalpa', 'test');
        $wrong = $guide->answer(
            new PublicConversationInput('laptop', 'published-guide-wrong-market', actorId: 'guest-1'),
            $wrongMarket,
        );
        self::assertSame([], $wrong['items']);
    }

    /** @return array{Tenant,Tenant,Product} */
    private function projectedFixture(string $country = 'CA'): array
    {
        $platform = Tenant::factory()->create();
        $assign = $country === 'JP' ? 'assignJapan' : 'assignCanada';
        GlobalMarketFixture::$assign($platform);

        $merchant = Tenant::factory()->create([
            'name' => 'Public Merchant',
            'slug' => 'public-merchant',
            'is_active' => true,
            'subscription_status' => 'active',
            'verification_status' => 'verified',
        ]);
        GlobalMarketFixture::$assign($merchant);
        $currency = $country === 'JP' ? 'JPY' : 'CAD';
        $price = $country === 'JP' ? 1500.00 : 120.00;
        $minor = $country === 'JP' ? 1500 : 12000;
        $product = Product::factory()->forTenant($merchant)->create([
            'name' => 'Published laptop',
            'description' => 'A public laptop offer.',
            'price' => $price,
            'price_minor' => $minor,
            'currency' => $currency,
            'is_active' => true,
            'is_visible' => true,
        ]);

        app(PublishedCommercialProjectionService::class)->projectCurrent();

        return [$platform, $merchant, $product];
    }

    private function market(Tenant $tenant): MarketContext
    {
        return app(TenantMarketResolver::class)->resolveExplicit($tenant);
    }
}
