<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Catalog\CachedProductReader;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class TenantProductCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_cache_is_tenant_partitioned_and_invalidates_by_generation(): void
    {
        Cache::flush();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $productA = Product::factory()->forTenant($tenantA)->create([
            'name' => 'Tenant A product',
            'is_featured' => true,
        ]);
        $productB = Product::factory()->forTenant($tenantB)->create([
            'name' => 'Tenant B product',
            'is_featured' => true,
        ]);
        $reader = app(CachedProductReader::class);

        self::assertSame([$productA->getKey()], $this->featuredKeys($reader, $tenantA));
        self::assertSame([$productB->getKey()], $this->featuredKeys($reader, $tenantB));

        $productA->forceFill(['is_featured' => false])->save();

        self::assertSame([], $this->featuredKeys($reader, $tenantA));
        self::assertSame([$productB->getKey()], $this->featuredKeys($reader, $tenantB));
    }

    /**
     * @return array<int, int>
     */
    private function featuredKeys(CachedProductReader $reader, Tenant $tenant): array
    {
        $context = app(TenantContext::class);
        $context->setTenant((string) $tenant->getKey());

        try {
            return $reader
                ->getFeaturedProducts((string) $tenant->getKey())
                ->modelKeys();
        } finally {
            $context->reset();
        }
    }
}
