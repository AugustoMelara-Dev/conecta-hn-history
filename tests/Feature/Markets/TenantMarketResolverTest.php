<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use App\Application\Markets\HondurasMarketBackfill;
use App\Application\Markets\MissingMarketContextException;
use App\Application\Markets\TenantMarketResolver;
use App\Models\City;
use App\Models\MarketPackVersion;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TenantMarketResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_canonical_tenant_market_after_backfill(): void
    {
        $tenant = Tenant::factory()->create();
        app(HondurasMarketBackfill::class)->run();

        $context = app(TenantMarketResolver::class)->resolve($tenant->refresh());

        self::assertSame('HN', $context->countryCode);
        self::assertSame('es-HN', $context->localeCode);
        self::assertSame('HNL', $context->currencyCode);
        self::assertSame('America/Tegucigalpa', $context->timezone);
        self::assertSame('tenant_market', $context->source);
    }

    public function test_it_shadow_reads_honduras_only_from_an_explicit_legacy_city(): void
    {
        $city = City::factory()->create();
        $tenant = Tenant::factory()->create(['city_id' => $city->id]);

        $context = app(TenantMarketResolver::class)->resolve($tenant);

        self::assertSame('HN', $context->countryCode);
        self::assertSame('legacy_honduras_city', $context->source);
    }

    public function test_primary_market_is_authoritative_while_legacy_resolve_keeps_honduras_shadow_read(): void
    {
        $city = City::factory()->create();
        $tenant = Tenant::factory()->create(['city_id' => $city->id]);
        app(HondurasMarketBackfill::class)->run();

        $tenant->load('primaryMarket');
        $resolver = app(TenantMarketResolver::class);

        self::assertSame('tenant_market', $resolver->resolve($tenant)->source);
        self::assertSame('tenant_market', $resolver->resolveExplicit($tenant)->source);

        DB::table('tenant_markets')->where('tenant_id', $tenant->id)->delete();
        $tenant->unsetRelation('primaryMarket');

        self::assertSame('legacy_honduras_city', $resolver->resolve($tenant)->source);
        $this->expectException(MissingMarketContextException::class);
        $resolver->resolveExplicit($tenant);
    }

    public function test_it_never_silently_assigns_a_market_without_canonical_or_legacy_evidence(): void
    {
        $tenant = Tenant::factory()->create(['city_id' => null]);
        DB::table('tenant_markets')->where('tenant_id', $tenant->id)->delete();

        $this->expectException(MissingMarketContextException::class);

        app(TenantMarketResolver::class)->resolve($tenant);
    }

    public function test_market_pack_capabilities_fail_closed(): void
    {
        app(HondurasMarketBackfill::class)->run();

        $version = MarketPackVersion::query()->firstOrFail();

        self::assertFalse($version->supports('channels.whatsapp'));
        self::assertFalse($version->supports('payments.card'));
    }
}
