<?php

declare(strict_types=1);

use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\Cache;

// ═══════════════════════════════════════════════════════════════════════════
// SAVED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('saved flushes tenant-scoped product cache tags', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 5;

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_5'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->saved($product);
});

// ═══════════════════════════════════════════════════════════════════════════
// DELETED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('deleted flushes tenant-scoped product cache tags', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 12;

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_12'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->deleted($product);
});

// ═══════════════════════════════════════════════════════════════════════════
// RESTORED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('restored flushes tenant-scoped product cache tags', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 3;

    Cache::shouldReceive('tags')
        ->once()
        ->with(['products', 'products_tenant_3'])
        ->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $observer = new ProductObserver;
    $observer->restored($product);
});

// ═══════════════════════════════════════════════════════════════════════════
// STRUCTURAL: Cache tag key format
// ═══════════════════════════════════════════════════════════════════════════

test('cache tags include tenant-specific key format', function () {
    $source = file_get_contents(
        (new ReflectionClass(ProductObserver::class))->getFileName()
    );

    expect($source)->toContain('products_tenant_{$product->tenant_id}');
    expect($source)->toContain("'products'");
});
