<?php

declare(strict_types=1);

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\TenantCacheManager;
use Tests\TestCase;

uses(TestCase::class);

// ═══════════════════════════════════════════════════════════════════════════
// SAVED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('saved rotates only the products tenant cache generation', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 5;
    $cacheKeys = Mockery::mock(TenantCacheManager::class);
    $cacheKeys->shouldReceive('rotateTenantVersion')->once()->with('5');

    $observer = new ProductObserver($cacheKeys);
    $observer->saved($product);
});

// ═══════════════════════════════════════════════════════════════════════════
// DELETED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('deleted rotates only the products tenant cache generation', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 12;
    $cacheKeys = Mockery::mock(TenantCacheManager::class);
    $cacheKeys->shouldReceive('rotateTenantVersion')->once()->with('12');

    $observer = new ProductObserver($cacheKeys);
    $observer->deleted($product);

    expect(true)->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// RESTORED EVENT: Cache Invalidation
// ═══════════════════════════════════════════════════════════════════════════

test('restored rotates only the products tenant cache generation', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->tenant_id = 3;
    $cacheKeys = Mockery::mock(TenantCacheManager::class);
    $cacheKeys->shouldReceive('rotateTenantVersion')->once()->with('3');

    $observer = new ProductObserver($cacheKeys);
    $observer->restored($product);

    expect(true)->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// STRUCTURAL: Cache tag key format
// ═══════════════════════════════════════════════════════════════════════════

test('cache invalidation uses the canonical generational tenant cache manager', function () {
    $source = file_get_contents(
        (new ReflectionClass(ProductObserver::class))->getFileName()
    );

    expect($source)->toContain('TenantCacheManager');
    expect($source)->toContain('rotateTenantVersion');
    expect($source)->not->toContain('Cache::tags');
});
