<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Dynamic\LayoutDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\RecordPersistenceService;
use App\Services\DynamicForms\SchemaRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DynamicRuntimeFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_blocks_schema_publish_when_runtime_is_disabled(): void
    {
        config()->set('dynamic-runtime.enabled', false);

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

        $this->expectException(\RuntimeException::class);

        app(SchemaRegistryService::class)->publish($entity, $publisher);
    }

    public function test_it_blocks_record_runtime_when_disabled(): void
    {
        config()->set('dynamic-runtime.record_runtime_enabled', false);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $entity = EntityDefinition::create([
            'tenant_id' => $tenant->getKey(),
            'owner_scope' => 'tenant',
            'module_key' => 'operations',
            'entity_key' => 'consultation',
            'name' => 'Consulta',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'patient_name',
            'field_type' => 'text',
            'label' => 'Paciente',
            'is_required' => true,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        app(SchemaRegistryService::class)->publish($entity, $owner);

        $this->expectException(ValidationException::class);

        app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['patient_name' => 'Paciente'],
            coreAttributes: [
                'tenant_id' => $tenant->getKey(),
                'owner_id' => $owner->getKey(),
            ],
        );
    }

    public function test_kill_switch_blocks_dynamic_runtime_even_if_flags_are_enabled(): void
    {
        config()->set('dynamic-runtime.enabled', true);
        config()->set('dynamic-runtime.record_runtime_enabled', true);
        config()->set('dynamic-runtime.kill_switch', true);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $entity = EntityDefinition::create([
            'tenant_id' => $tenant->getKey(),
            'owner_scope' => 'tenant',
            'module_key' => 'operations',
            'entity_key' => 'consultation',
            'name' => 'Consulta',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'patient_name',
            'field_type' => 'text',
            'label' => 'Paciente',
            'is_required' => true,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $this->expectException(\RuntimeException::class);

        app(SchemaRegistryService::class)->publish($entity, $owner);
    }
}
