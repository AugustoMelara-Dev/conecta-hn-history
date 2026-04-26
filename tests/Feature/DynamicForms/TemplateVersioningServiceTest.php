<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Events\DynamicForms\TemplatePublished;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Models\User;
use App\Services\DynamicForms\TemplateVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class TemplateVersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_template_versions_and_dispatches_domain_event(): void
    {
        Event::fake([TemplatePublished::class]);

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
                ['type' => 'kv', 'items' => [['Paciente', '{{record.patient_name}}']]],
            ],
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $version = app(TemplateVersioningService::class)->publish($template, $publisher);

        $template->refresh();

        $this->assertSame(1, $version->version_number);
        $this->assertSame('published', $template->status);
        $this->assertSame((string) $version->getKey(), (string) $template->active_version_id);

        Event::assertDispatched(TemplatePublished::class, function (TemplatePublished $event) use ($template): bool {
            return $event->templateId === (string) $template->getKey()
                && $event->templateKey === 'visit_summary'
                && $event->versionNumber === 1;
        });
    }
}
