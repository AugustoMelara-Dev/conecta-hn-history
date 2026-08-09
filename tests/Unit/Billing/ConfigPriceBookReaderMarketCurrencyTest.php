<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Application\Markets\MarketContext;
use App\Infrastructure\Billing\Global\ConfigPriceBookReader;
use Tests\TestCase;

final class ConfigPriceBookReaderMarketCurrencyTest extends TestCase
{
    public function test_price_book_must_match_the_explicit_tenant_market_currency(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match the tenant market');

        (new ConfigPriceBookReader)->forMarket(new MarketContext('HN', 'es-HN', 'USD', 'America/Tegucigalpa', 'test'));
    }
}
