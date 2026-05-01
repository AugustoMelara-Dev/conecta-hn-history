<?php

declare(strict_types=1);

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\TenantCacheManager;

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

    $cacheManager = Mockery::mock(TenantCacheManager::class);
    $cacheManager->shouldReceive('rotateTenantVersion')
        ->once()
        ->with('5')
        ->andReturn(123);
    app()->instance(TenantCacheManager::class, $cacheManager);

    $observer = new ProductObserver;
    $observer->saved($product);
});

test('deleted flushes tenant-scoped product cache tags', function () {
    $product = makeObservedProduct('12');

    $cacheManager = Mockery::mock(TenantCacheManager::class);
    $cacheManager->shouldReceive('rotateTenantVersion')
        ->once()
        ->with('12')
        ->andReturn(123);
    app()->instance(TenantCacheManager::class, $cacheManager);

    $observer = new ProductObserver;
    $observer->deleted($product);
});

test('restored flushes tenant-scoped product cache tags', function () {
    $product = makeObservedProduct('3');

    $cacheManager = Mockery::mock(TenantCacheManager::class);
    $cacheManager->shouldReceive('rotateTenantVersion')
        ->once()
        ->with('3')
        ->andReturn(123);
    app()->instance(TenantCacheManager::class, $cacheManager);

    $observer = new ProductObserver;
    $observer->restored($product);
});

test('product observer uses driver agnostic tenant version rotation', function () {
    $source = file_get_contents((string) (new ReflectionClass(ProductObserver::class))->getFileName());

    expect($source)->toContain('rotateTenantVersion($product->tenant_id)');
    expect($source)->not->toContain('Cache::tags');
});
