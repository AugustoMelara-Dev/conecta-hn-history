<?php

declare(strict_types=1);

use App\Application\Shared\Contracts\TenantCacheManagerInterface;
use App\Infrastructure\Catalog\CachedProductReader;
use Illuminate\Support\Facades\Cache;

test('cached product reader uses driver agnostic versioned cache keys', function () {
    $cacheManager = Mockery::mock(TenantCacheManagerInterface::class);
    $cacheManager->shouldReceive('makeKey')
        ->once()
        ->with(42, 'featured_limit_3')
        ->andReturn('tenant_42_v123_featured_limit_3');

    Cache::shouldReceive('remember')
        ->once()
        ->with('tenant_42_v123_featured_limit_3', 3600, Mockery::type(Closure::class))
        ->andReturn([['name' => 'Producto cacheado']]);
    Cache::shouldReceive('tags')->never();

    $reader = new CachedProductReader($cacheManager);

    expect($reader->getFeaturedProducts(42, 3))->toBe([['name' => 'Producto cacheado']]);
});
