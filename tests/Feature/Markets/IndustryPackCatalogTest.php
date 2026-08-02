<?php

declare(strict_types=1);

namespace Tests\Feature\Markets;

use App\Application\Markets\HondurasMarketBackfill;
use App\Application\Markets\IndustryPackCatalog;
use App\Application\Markets\MarketContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IndustryPackCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_honduras_is_one_explicit_pack_and_not_a_global_fallback(): void
    {
        app(HondurasMarketBackfill::class)->run();

        $catalog = app(IndustryPackCatalog::class);
        $honduras = $catalog->forMarket(new MarketContext(
            'HN',
            'es-HN',
            'HNL',
            'America/Tegucigalpa',
            'tenant_market',
        ));

        self::assertSame('hn', $honduras['packKey']);
        self::assertSame(['es-HN'], $honduras['locales']);
        self::assertSame(['HNL'], $honduras['currencies']);
        self::assertSame('America/Tegucigalpa', $honduras['timezone']);
        self::assertArrayHasKey('schemaReferences', $honduras);

        self::assertSame([], $catalog->forMarket(new MarketContext(
            'CA',
            'en-CA',
            'CAD',
            'America/Toronto',
            'tenant_market',
        )));
    }

    public function test_unsupported_currency_does_not_resolve_an_industry_pack(): void
    {
        app(HondurasMarketBackfill::class)->run();

        self::assertSame([], app(IndustryPackCatalog::class)->forMarket(new MarketContext(
            'HN',
            'es-HN',
            'USD',
            'America/Tegucigalpa',
            'tenant_market',
        )));
    }
}
