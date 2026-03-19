<?php

declare(strict_types=1);

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Cache;

function makeObservedProduct(int|string $tenantId): Product
{
    $product = new Product;
    $product->forceFill([
        'tenant_id' => (string) $tenantId,
    ]);

    return $product;
}

test('saved flushes tenant-scoped product cache tags', function () {
    $product = makeObservedProduct('5');

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_5'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->saved($product);
});

test('deleted flushes tenant-scoped product cache tags', function () {
    $product = makeObservedProduct('12');

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_12'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->deleted($product);
});

test('restored flushes tenant-scoped product cache tags', function () {
    $product = makeObservedProduct('3');

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_3'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->restored($product);
});

test('cache tags include tenant-specific key format', function () {
    $source = file_get_contents((string) (new ReflectionClass(ProductObserver::class))->getFileName());

    expect($source)->toContain('products_tenant_{$product->tenant_id}');
    expect($source)->toContain("'products'");
});
