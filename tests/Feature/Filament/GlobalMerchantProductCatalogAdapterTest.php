<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Products\MerchantProductCatalogAdapter;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class GlobalMerchantProductCatalogAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_japanese_product_uses_market_currency_and_zero_decimal_minor_units(): void
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignJapan($tenant);

        $adapter = app(MerchantProductCatalogAdapter::class);
        $product = $adapter->create($tenant, [
            'currency' => 'HNL',
            'name' => 'Workshop',
            'price' => '123',
            'stock_quantity' => 1,
        ], 'product-jp-create-1');

        self::assertSame('JPY', $product->currency);
        self::assertSame(123, (int) $product->price_minor);
        self::assertSame(123.0, (float) $product->getRawOriginal('price'));

        $copy = $adapter->clone($tenant, $product, 'product-jp-clone-1');
        self::assertSame('JPY', $copy->currency);
        self::assertSame(123, (int) $copy->price_minor);
    }

    public function test_market_decimal_contract_rejects_fractional_jpy_price(): void
    {
        $tenant = Tenant::factory()->create();
        GlobalMarketFixture::assignJapan($tenant);

        $this->expectException(ValidationException::class);
        app(MerchantProductCatalogAdapter::class)->create($tenant, [
            'name' => 'Unsafe workshop',
            'price' => '123.45',
        ], 'product-jp-create-invalid');
    }
}
