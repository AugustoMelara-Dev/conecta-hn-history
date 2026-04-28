<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\EntityDefinitionClonerService;
use App\Services\DynamicForms\PrintTemplateClonerService;
use App\Services\DynamicForms\RecordPersistenceService;
use App\Services\DynamicForms\SchemaRegistryService;
use App\Services\DynamicForms\TemplateRenderService;
use App\Services\DynamicForms\TemplateVersioningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DynamicRecordPrintFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_supports_admin_blueprint_clone_record_and_print_pdf_flow(): void
    {
        $superadmin = User::factory()->create();
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $globalEntity = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'operations',
            'entity_key' => 'visit',
            'name' => 'Visita',
            'status' => 'published',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $globalEntity->getKey(),
            'field_key' => 'patient_name',
            'field_type' => 'text',
            'label' => 'Paciente',
            'is_required' => true,
            'storage_mode' => 'indexed',
            'is_indexed' => true,
            'sort_order' => 1,
            'status' => 'published',
        ]);

        app(SchemaRegistryService::class)->publish($globalEntity, $superadmin);

        $globalTemplate = DynamicPrintTemplate::create([
            'tenant_id' => null,
            'entity_definition_id' => $globalEntity->getKey(),
            'owner_scope' => 'superadmin',
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'name' => 'Resumen visita',
            'blocks_json' => [
                ['type' => 'header', 'content' => '{{tenant.name}}'],
                ['type' => 'kv', 'items' => [['Paciente', '{{record.patient_name}}']]],
                ['type' => 'footer', 'content' => 'Estado {{record.status}}'],
            ],
            'status' => 'published',
            'is_locked' => false,
        ]);

        $this->actingAsTenant((string) $tenant->getKey());

        $tenantEntity = app(EntityDefinitionClonerService::class)->cloneForTenant(
            $globalEntity,
            (string) $tenant->getKey(),
            (string) $owner->getKey()
        );

        $tenantTemplate = app(PrintTemplateClonerService::class)->cloneForTenant(
            $globalTemplate,
            (string) $tenant->getKey(),
            (string) $owner->getKey()
        );

        $this->assertSame((string) $tenantEntity->getKey(), (string) $tenantTemplate->entity_definition_id);

        app(SchemaRegistryService::class)->publish($tenantEntity->fresh(), $owner);
        app(TemplateVersioningService::class)->publish($tenantTemplate->fresh(), $owner);

        $record = app(RecordPersistenceService::class)->upsert(
            entityDefinition: $tenantEntity->fresh(),
            payload: ['patient_name' => 'Maria Lopez'],
            coreAttributes: [
                'tenant_id' => $tenant->getKey(),
                'owner_id' => $owner->getKey(),
                'status' => 'active',
            ],
        );

        $schema = app(SchemaRegistryService::class)->getActiveSchema($tenantEntity->fresh());
        $pdf = app(TemplateRenderService::class)->renderPdf(
            $tenantTemplate->fresh(),
            [
                'tenant' => $tenant->toArray(),
                'record' => [
                    'patient_name' => data_get($record->payload_json, 'patient_name'),
                    'status' => $record->status,
                ],
            ],
            $schema
        );

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
