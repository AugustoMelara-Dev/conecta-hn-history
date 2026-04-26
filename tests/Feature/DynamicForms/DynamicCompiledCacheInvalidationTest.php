<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use App\Events\DynamicForms\SchemaPublished;
use App\Events\DynamicForms\TemplatePublished;
use App\Events\DynamicForms\TenantBlueprintCloned;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class DynamicCompiledCacheInvalidationTest extends TestCase
{
    public function test_schema_publish_event_invalidates_schema_cache_key(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);
        $key = $cache->schemaCacheKey('tenant-1', 'appointment', 4);
        $cache->rememberSchema('tenant-1', 'appointment', 4, static fn (): array => ['cached' => true]);

        $this->assertTrue(Cache::has($key));

        event(new SchemaPublished(
            entityDefinitionId: 'entity-1',
            entityKey: 'appointment',
            versionNumber: 4,
            tenantId: 'tenant-1',
            publishedBy: 'user-1',
        ));

        $this->assertFalse(Cache::has($key));
    }

    public function test_template_publish_event_invalidates_template_cache_key(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);
        $key = $cache->templateCacheKey('tenant-2', 'visit_summary', 7);
        $cache->rememberTemplate('tenant-2', 'visit_summary', 7, static fn (): array => ['cached' => true]);

        $this->assertTrue(Cache::has($key));

        event(new TemplatePublished(
            templateId: 'template-1',
            templateKey: 'visit_summary',
            versionNumber: 7,
            tenantId: 'tenant-2',
            publishedBy: 'user-2',
        ));

        $this->assertFalse(Cache::has($key));
    }

    public function test_tenant_blueprint_clone_flushes_compiled_cache_scope_for_tenant(): void
    {
        $cache = app(DynamicCompiledCacheInterface::class);
        $schemaKey = $cache->schemaCacheKey('tenant-3', 'order', 2);
        $templateKey = $cache->templateCacheKey('tenant-3', 'receipt', 8);

        $cache->rememberSchema('tenant-3', 'order', 2, static fn (): array => ['cached' => true]);
        $cache->rememberTemplate('tenant-3', 'receipt', 8, static fn (): array => ['cached' => true]);

        $this->assertTrue(Cache::has($schemaKey));
        $this->assertTrue(Cache::has($templateKey));

        event(new TenantBlueprintCloned(
            blueprintType: 'entity_definition',
            sourceId: 'source-1',
            targetId: 'target-1',
            tenantId: 'tenant-3',
            actorId: 'user-3',
        ));

        $this->assertFalse(Cache::has($schemaKey));
        $this->assertFalse(Cache::has($templateKey));
    }
}

