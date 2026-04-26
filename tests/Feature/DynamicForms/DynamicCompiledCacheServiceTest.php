<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class DynamicCompiledCacheServiceTest extends TestCase
{
    public function test_it_memoizes_schema_payload_by_tenant_entity_version(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);
        $calls = 0;

        $first = $cache->rememberSchema('tenant-1', 'appointment', 3, function () use (&$calls): array {
            $calls++;

            return ['compiled' => true, 'version' => 3];
        });

        $second = $cache->rememberSchema('tenant-1', 'appointment', 3, function () use (&$calls): array {
            $calls++;

            return ['compiled' => false, 'version' => 999];
        });

        $this->assertSame(1, $calls);
        $this->assertSame($first, $second);
        $this->assertTrue(Cache::has($cache->schemaCacheKey('tenant-1', 'appointment', 3)));
    }

    public function test_it_forgets_schema_cache_by_exact_version(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);
        $cache->rememberSchema('tenant-2', 'lead', 1, static fn (): array => ['ok' => true]);

        $key = $cache->schemaCacheKey('tenant-2', 'lead', 1);
        $this->assertTrue(Cache::has($key));

        $cache->forgetSchema('tenant-2', 'lead', 1);

        $this->assertFalse(Cache::has($key));
    }

    public function test_it_flushes_only_the_target_tenant_cache_scope(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);

        $cache->rememberSchema('tenant-A', 'order', 2, static fn (): array => ['tenant' => 'A']);
        $cache->rememberTemplate('tenant-A', 'receipt', 5, static fn (): array => ['tenant' => 'A']);
        $cache->rememberSchema(null, 'order', 2, static fn (): array => ['tenant' => 'global']);

        $tenantSchemaKey = $cache->schemaCacheKey('tenant-A', 'order', 2);
        $tenantTemplateKey = $cache->templateCacheKey('tenant-A', 'receipt', 5);
        $globalSchemaKey = $cache->schemaCacheKey(null, 'order', 2);

        $cache->flushTenant('tenant-A');

        $this->assertFalse(Cache::has($tenantSchemaKey));
        $this->assertFalse(Cache::has($tenantTemplateKey));
        $this->assertTrue(Cache::has($globalSchemaKey));
    }
}

