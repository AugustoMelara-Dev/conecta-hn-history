<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\Dynamic\LayoutDefinition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DynamicFormsLintCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_in_strict_mode_when_lint_errors_exist(): void
    {
        $this->seedEntityWithOrphanLayoutField();

        $exitCode = Artisan::call('dynamic-forms:lint', [
            '--strict' => true,
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($report['errors']);
        $this->assertSame('orphan_layout_key', $report['errors'][0]['category']);
    }

    public function test_it_downgrades_lint_errors_to_warnings_in_non_strict_mode(): void
    {
        $this->seedEntityWithOrphanLayoutField();

        $exitCode = Artisan::call('dynamic-forms:lint', [
            '--strict' => false,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $report['errors']);
        $this->assertNotEmpty($report['warnings']);
        $this->assertSame('orphan_layout_key', $report['warnings'][0]['category']);
    }

    public function test_changed_only_skips_stale_definitions(): void
    {
        $entity = $this->seedEntityWithOrphanLayoutField();

        $staleTime = now()->subDays(3);
        $entity->forceFill(['updated_at' => $staleTime])->save();

        $exitCode = Artisan::call('dynamic-forms:lint', [
            '--strict' => true,
            '--changed-only' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $report['errors']);
        $this->assertSame(0, (int) $report['meta']['entities_scanned']);
        $this->assertTrue((bool) $report['meta']['changed_only']);
    }

    private function seedEntityWithOrphanLayoutField(): EntityDefinition
    {
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();

        $this->actingAsTenant((string) $tenant->getKey());

        $entity = EntityDefinition::create([
            'tenant_id' => $tenant->getKey(),
            'owner_scope' => 'tenant',
            'module_key' => 'operations',
            'entity_key' => 'lint_visit',
            'name' => 'Lint Visit',
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

        LayoutDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'layout_key' => 'form',
            'layout_json' => [
                'field_order' => ['non_existing_field_key'],
            ],
            'status' => 'draft',
        ]);

        return $entity;
    }
}

