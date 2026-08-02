<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Global;

use App\Application\Markets\MarketContext;
use App\Domain\Billing\Global\PriceBookReaderInterface;
use App\Infrastructure\Billing\Global\ConfigPriceBookReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GlobalPriceBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_price_book_is_versioned_currency_scoped_and_preserves_explore_as_sandbox_only(): void
    {
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
                    'checkout_mode' => 'external',
                ],
            ],
        ]);

        self::assertTrue(interface_exists(PriceBookReaderInterface::class));
        self::assertTrue(class_exists(ConfigPriceBookReader::class));

        $reader = new ConfigPriceBookReader;
        $book = $reader->forMarket(new MarketContext('CA', 'en-CA', 'CAD', 'America/Toronto', 'tenant_market'));

        self::assertSame('research-v1', $book->version());
        self::assertSame('ca', $book->marketKey());
        self::assertSame('CAD', $book->currencyCode());
        self::assertSame(0, $book->plan('explore')->monthlyMinor());
        self::assertTrue($book->plan('explore')->sandboxOnly());
        self::assertFalse($book->plan('explore')->liveEntitled());
        self::assertSame(4900, $book->plan('launch')->monthlyMinor());
        self::assertSame(48804, $book->plan('launch')->annualMinor());
        self::assertSame(25.0, $book->plan('launch')->includedCredits());
        self::assertSame('external', $book->plan('enterprise')->checkoutMode());
    }

    public function test_unknown_market_fails_closed_without_reintroducing_honduras_currency_defaults(): void
    {
        config()->set('dovra_billing.price_books', []);

        self::assertTrue(class_exists(ConfigPriceBookReader::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('price book');

        (new ConfigPriceBookReader)->forMarket(
            new MarketContext('ZZ', 'en-ZZ', 'ZZD', 'UTC', 'tenant_market'),
        );
    }
}
