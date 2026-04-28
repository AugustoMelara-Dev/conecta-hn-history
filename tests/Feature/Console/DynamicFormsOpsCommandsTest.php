<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DynamicForms\RecordPersistenceService;
use App\Services\DynamicForms\SchemaRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DynamicFormsOpsCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_report_returns_runtime_and_usage_metrics(): void
    {
        [$tenant, $entity] = $this->seedDynamicFixture();

        DynamicPrintTemplate::create([
            'tenant_id' => $tenant->getKey(),
            'entity_definition_id' => $entity->getKey(),
            'owner_scope' => 'tenant',
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'name' => 'Resumen',
            'blocks_json' => [
                ['type' => 'paragraph', 'content' => '{{record.patient_name}}'],
            ],
            'status' => 'published',
            'is_locked' => false,
        ]);

        $exitCode = Artisan::call('dynamic-forms:ops-report', [
            '--tenant' => (string) $tenant->getKey(),
            '--module' => 'operations',
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('operations', $report['module_filter']);
        $this->assertSame((string) $tenant->getKey(), $report['tenant_filter']);
        $this->assertSame(1, (int) $report['entities']['total']);
        $this->assertSame(1, (int) $report['templates']['published']);
        $this->assertSame(1, (int) $report['records']['total']);
    }

    public function test_reindex_command_repairs_index_values_for_existing_records(): void
    {
        [$tenant, $entity, $recordId] = $this->seedDynamicFixture();

        DB::table('dynamic_record_index_values')
            ->where('dynamic_record_id', $recordId)
            ->where('field_key', 'patient_name')
            ->update(['value_string' => 'corrupt']);

        $exitCode = Artisan::call('dynamic-forms:reindex', [
            'entity' => (string) $entity->getKey(),
            '--tenant' => (string) $tenant->getKey(),
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, (int) $report['processed']);
        $this->assertSame(0, (int) $report['failed']);

        $this->assertDatabaseHas('dynamic_record_index_values', [
            'dynamic_record_id' => $recordId,
            'field_key' => 'patient_name',
            'value_string' => 'Maria Lopez',
        ]);
    }

    /**
     * @return array{0: Tenant, 1: EntityDefinition, 2?: string}
     */
    private function seedDynamicFixture(): array
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $this->actingAsTenant((string) $tenant->getKey());

        $entity = EntityDefinition::create([
            'tenant_id' => $tenant->getKey(),
            'owner_scope' => 'tenant',
            'module_key' => 'operations',
            'entity_key' => 'visit',
            'name' => 'Visita',
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

        $record = app(RecordPersistenceService::class)->upsert(
            entityDefinition: $entity->fresh(),
            payload: ['patient_name' => 'Maria Lopez'],
            coreAttributes: [
                'tenant_id' => $tenant->getKey(),
                'owner_id' => $owner->getKey(),
                'status' => 'active',
            ],
        );

        return [$tenant, $entity->fresh(), (string) $record->getKey()];
    }
}
