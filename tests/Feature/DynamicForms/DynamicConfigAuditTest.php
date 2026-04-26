<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Models\Dynamic\DynamicConfigAudit;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Dynamic\LayoutDefinition;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\EntityDefinitionClonerService;
use App\Services\DynamicForms\SchemaRegistryService;
use App\Services\DynamicForms\TemplateVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DynamicConfigAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_schema_publish_audit_and_mirrors_activity_log(): void
    {
        config(['activitylog.enabled' => true]);

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
            'layout_json' => ['field_order' => ['customer_name']],
            'status' => 'draft',
        ]);

        app(SchemaRegistryService::class)->publish($entity, $publisher);

        $audit = DynamicConfigAudit::query()
            ->where('action_key', 'schema_published')
            ->where('resource_type', 'entity_definition')
            ->where('resource_id', (string) $entity->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $publisher->getKey(), (string) $audit->actor_id);
        $this->assertSame(1, (int) data_get($audit->metadata_json, 'version_number'));
        $this->assertNotEmpty($audit->after_json);
        $this->assertNotEmpty($audit->json_patch);

        $this->assertTrue(
            DB::table((string) config('activitylog.table_name', 'activity_log'))
                ->where('description', 'dynamic_forms.schema_published')
                ->exists()
        );
    }

    public function test_it_persists_template_publish_with_before_after_and_diff(): void
    {
        config(['activitylog.enabled' => true]);

        $publisher = User::factory()->superAdmin()->create();

        $entity = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'services',
            'entity_key' => 'appointment',
            'name' => 'Cita',
            'status' => 'draft',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'patient_name',
            'field_type' => 'text',
            'label' => 'Paciente',
            'storage_mode' => 'json',
            'is_required' => false,
            'is_indexed' => false,
            'sort_order' => 1,
            'status' => 'published',
        ]);

        $template = DynamicPrintTemplate::create([
            'tenant_id' => null,
            'entity_definition_id' => $entity->getKey(),
            'owner_scope' => 'superadmin',
            'template_key' => 'visit_summary',
            'action_key' => 'default',
            'name' => 'Resumen consulta',
            'blocks_json' => [
                ['type' => 'header', 'content' => '{{tenant.name}}'],
            ],
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $service = app(TemplateVersioningService::class);
        $service->publish($template, $publisher);

        $template->forceFill([
            'blocks_json' => [
                ['type' => 'header', 'content' => '{{tenant.name}}'],
                ['type' => 'kv', 'items' => [['Paciente', '{{record.patient_name}}']]],
            ],
        ])->save();

        config(['dynamic-forms.contract_defaults.template.semver' => '1.1.0']);
        $service->publish($template->refresh(), $publisher);

        $audit = DynamicConfigAudit::query()
            ->where('action_key', 'template_published')
            ->where('resource_type', 'print_template')
            ->where('resource_id', (string) $template->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(2, (int) data_get($audit->metadata_json, 'version_number'));
        $this->assertNotEmpty($audit->before_json);
        $this->assertNotEmpty($audit->after_json);
        $this->assertNotEmpty($audit->json_patch);

        $this->assertTrue(
            DB::table((string) config('activitylog.table_name', 'activity_log'))
                ->where('description', 'dynamic_forms.template_published')
                ->exists()
        );
    }

    public function test_it_persists_clone_audit_with_source_target_traceability(): void
    {
        config(['activitylog.enabled' => true]);

        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $source = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'crm',
            'entity_key' => 'lead',
            'name' => 'Lead',
            'status' => 'published',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $source->getKey(),
            'field_key' => 'customer_name',
            'field_type' => 'text',
            'label' => 'Cliente',
            'is_required' => false,
            'storage_mode' => 'json',
            'is_indexed' => false,
            'sort_order' => 1,
            'status' => 'published',
        ]);

        LayoutDefinition::create([
            'entity_definition_id' => $source->getKey(),
            'layout_key' => 'form',
            'layout_json' => ['field_order' => ['customer_name']],
            'status' => 'published',
        ]);

        $cloned = app(EntityDefinitionClonerService::class)->cloneForTenant(
            $source,
            (string) $tenant->getKey(),
            (string) $owner->getKey(),
        );

        $audit = DynamicConfigAudit::query()
            ->where('action_key', 'tenant_blueprint_cloned')
            ->where('resource_type', 'entity_definition')
            ->where('resource_id', (string) $cloned->getKey())
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $tenant->getKey(), (string) $audit->tenant_id);
        $this->assertSame((string) $owner->getKey(), (string) $audit->actor_id);
        $this->assertSame('entity_definition', data_get($audit->metadata_json, 'blueprint_type'));
        $this->assertSame((string) $source->getKey(), data_get($audit->metadata_json, 'source_id'));
        $this->assertSame((string) $cloned->getKey(), data_get($audit->metadata_json, 'target_id'));

        $this->assertTrue(
            DB::table((string) config('activitylog.table_name', 'activity_log'))
                ->where('description', 'dynamic_forms.tenant_blueprint_cloned')
                ->exists()
        );
    }
}
