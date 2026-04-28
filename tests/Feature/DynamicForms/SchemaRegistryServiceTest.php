<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Events\DynamicForms\SchemaPublished;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Dynamic\LayoutDefinition;
use App\Models\User;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;
use App\Services\DynamicForms\SchemaRegistryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class SchemaRegistryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_builds_and_publishes_schema_versions_from_definition_tables(): void
    {
        Event::fake([SchemaPublished::class]);

        $publisher = User::factory()->superAdmin()->create();
        $entity = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'crm',
            'entity_key' => 'lead',
            'name' => 'Lead',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'customer_name',
            'field_type' => 'text',
            'label' => 'Cliente',
            'is_required' => true,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'validation_rules' => ['max:120'],
            'status' => 'draft',
        ]);

        LayoutDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'layout_key' => 'form',
            'layout_json' => [
                'field_order' => ['customer_name'],
            ],
            'status' => 'draft',
        ]);

        $service = app(SchemaRegistryService::class);
        $version = $service->publish($entity, $publisher);

        $entity->refresh();

        $this->assertSame(1, $version->version_number);
        $this->assertSame('published', $entity->status);
        $this->assertSame((string) $version->getKey(), (string) $entity->active_schema_version_id);

        $schema = $service->getActiveSchema($entity);

        $this->assertSame('lead', data_get($schema, 'entity.entity_key'));
        $this->assertCount(1, data_get($schema, 'fields'));
        $this->assertSame('customer_name', data_get($schema, 'fields.0.field_key'));
        $this->assertSame('form', data_get($schema, 'layouts.0.layout_key'));

        Event::assertDispatched(SchemaPublished::class, function (SchemaPublished $event) use ($entity): bool {
            return $event->entityDefinitionId === (string) $entity->getKey()
                && $event->entityKey === 'lead'
                && $event->versionNumber === 1;
        });
    }

    public function test_it_rejects_publish_when_hooks_use_non_whitelisted_actions(): void
    {
        $publisher = User::factory()->superAdmin()->create();
        $entity = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'crm',
            'entity_key' => 'lead',
            'name' => 'Lead',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'customer_name',
            'field_type' => 'text',
            'label' => 'Cliente',
            'is_required' => true,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'hooks_json' => [
                ['action' => 'execute_shell'],
            ],
            'status' => 'draft',
        ]);

        LayoutDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'layout_key' => 'form',
            'layout_json' => [
                'field_order' => ['customer_name'],
            ],
            'status' => 'draft',
        ]);

        $this->expectException(InvalidDynamicTemplateException::class);

        app(SchemaRegistryService::class)->publish($entity, $publisher);
    }
}
