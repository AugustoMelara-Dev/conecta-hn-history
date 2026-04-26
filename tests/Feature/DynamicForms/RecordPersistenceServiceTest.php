<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Events\DynamicForms\DynamicRecordPersisted;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\DynamicSearchService;
use App\Services\DynamicForms\RecordPersistenceService;
use App\Services\DynamicForms\SchemaRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RecordPersistenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_payload_and_syncs_index_values_for_dynamic_records(): void
    {
        Event::fake([DynamicRecordPersisted::class]);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $this->actingAsTenant((string) $tenant->getKey());

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

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'notes',
            'field_type' => 'textarea',
            'label' => 'Notas',
            'is_required' => false,
            'storage_mode' => 'json',
            'is_indexed' => false,
            'sort_order' => 2,
            'status' => 'draft',
        ]);

        app(SchemaRegistryService::class)->publish($entity, $owner);

        $persistence = app(RecordPersistenceService::class);
        $record = $persistence->upsert(
            entityDefinition: $entity->fresh(),
            payload: [
                'patient_name' => 'Maria Lopez',
                'notes' => 'Control trimestral',
            ],
            coreAttributes: [
                'tenant_id' => $tenant->getKey(),
                'owner_id' => $owner->getKey(),
                'status' => 'active',
            ],
        );

        $this->assertDatabaseHas('dynamic_records', [
            'id' => $record->getKey(),
            'tenant_id' => $tenant->getKey(),
            'entity_definition_id' => $entity->getKey(),
        ]);

        $this->assertDatabaseHas('dynamic_record_index_values', [
            'dynamic_record_id' => $record->getKey(),
            'field_key' => 'patient_name',
            'value_string' => 'Maria Lopez',
        ]);

        Event::assertDispatched(DynamicRecordPersisted::class, function (DynamicRecordPersisted $event) use ($record, $entity, $tenant): bool {
            return $event->recordId === (string) $record->getKey()
                && $event->entityDefinitionId === (string) $entity->getKey()
                && $event->tenantId === (string) $tenant->getKey()
                && $event->operation === 'created';
        });

        $updated = $persistence->upsert(
            entityDefinition: $entity->fresh(),
            payload: [
                'patient_name' => 'Ana Ruiz',
                'notes' => 'Seguimiento semanal',
            ],
            coreAttributes: [
                'tenant_id' => $tenant->getKey(),
                'owner_id' => $owner->getKey(),
                'status' => 'active',
            ],
            record: $record,
        );

        $this->assertSame('Ana Ruiz', data_get($updated->payload_json, 'patient_name'));
        $this->assertDatabaseHas('dynamic_record_index_values', [
            'dynamic_record_id' => $record->getKey(),
            'field_key' => 'patient_name',
            'value_string' => 'Ana Ruiz',
        ]);
    }

    public function test_dynamic_search_service_uses_indexed_fields(): void
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $this->actingAsTenant((string) $tenant->getKey());

        $entity = EntityDefinition::create([
            'tenant_id' => $tenant->getKey(),
            'owner_scope' => 'tenant',
            'module_key' => 'crm',
            'entity_key' => 'lead',
            'name' => 'Lead',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'segment',
            'field_type' => 'select',
            'label' => 'Segmento',
            'is_required' => false,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        app(SchemaRegistryService::class)->publish($entity, $owner);
        $persistence = app(RecordPersistenceService::class);

        $persistence->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['segment' => 'vip'],
            coreAttributes: ['tenant_id' => $tenant->getKey(), 'owner_id' => $owner->getKey()]
        );

        $persistence->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['segment' => 'nuevo'],
            coreAttributes: ['tenant_id' => $tenant->getKey(), 'owner_id' => $owner->getKey()]
        );

        $result = app(DynamicSearchService::class)->search(
            entityDefinition: $entity->fresh(),
            tenantId: (string) $tenant->getKey(),
            filters: ['segment' => 'vip'],
            perPage: 50,
        );

        $this->assertSame(1, $result->total());
        $this->assertSame('vip', data_get($result->items()[0]->payload_json, 'segment'));
    }

    public function test_it_blocks_cross_tenant_updates_for_dynamic_records(): void
    {
        $ownerA = User::factory()->tenantOwner()->create();
        $tenantA = Tenant::factory()->forUser($ownerA)->create();

        $ownerB = User::factory()->tenantOwner()->create();
        $tenantB = Tenant::factory()->forUser($ownerB)->create();

        $this->actingAsTenant((string) $tenantA->getKey());

        $entity = EntityDefinition::create([
            'tenant_id' => $tenantA->getKey(),
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

        app(SchemaRegistryService::class)->publish($entity, $ownerA);

        $record = app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['patient_name' => 'Paciente A'],
            coreAttributes: [
                'tenant_id' => $tenantA->getKey(),
                'owner_id' => $ownerA->getKey(),
            ],
        );

        $this->expectException(ValidationException::class);

        app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['patient_name' => 'Paciente B'],
            coreAttributes: [
                'tenant_id' => $tenantB->getKey(),
                'owner_id' => $ownerB->getKey(),
            ],
            record: $record,
        );
    }
}
