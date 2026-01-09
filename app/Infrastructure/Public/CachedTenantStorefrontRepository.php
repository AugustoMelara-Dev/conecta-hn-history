<?php

declare(strict_types=1);

namespace App\Infrastructure\Public;

use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * CachedTenantStorefrontRepository - Transparent Cache Decorator
 * 
 * ARCHITECTURE:
 * - Decorator Pattern: Wraps the inner repository.
 * - SRP: Handles Caching logic only. Inner repository handles Eloquent.
 * - Read-Through: Misses fetch from DB and populate Cache.
 */
final class CachedTenantStorefrontRepository implements TenantStorefrontRepository
{
    private const TTL_SECONDS = 3600; // 1 Hour

    public function __construct(
        private readonly TenantStorefrontRepository $inner
    ) {}

    public function findBySlug(string $slug): ?Tenant
    {
        // Cache Key Strategy: storefront:{slug}
        $key = "storefront:{$slug}";

        return Cache::remember($key, self::TTL_SECONDS, function () use ($slug) {
            return $this->inner->findBySlug($slug);
        });
    }
}
