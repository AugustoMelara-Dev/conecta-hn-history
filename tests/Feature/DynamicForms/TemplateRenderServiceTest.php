<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\FieldDefinition;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;
use App\Services\DynamicForms\TemplateRenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TemplateRenderServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_renders_blocks_with_safe_placeholders(): void
    {
        $entity = EntityDefinition::create([
            'tenant_id' => null,
            'owner_scope' => 'superadmin',
            'module_key' => 'services',
            'entity_key' => 'appointment',
            'name' => 'Cita',
            'status' => 'published',
            'allow_tenant_overrides' => true,
            'is_locked' => false,
        ]);

        FieldDefinition::create([
            'entity_definition_id' => $entity->getKey(),
            'field_key' => 'patient_name',
            'field_type' => 'text',
            'label' => 'Paciente',
            'is_required' => false,
            'storage_mode' => 'json',
            'is_indexed' => false,
            'sort_order' => 1,
            'status' => 'published',
        ]);

        $template = DynamicPrintTemplate::create([
            'tenant_id' => null,
            'entity_definition_id' => $entity->getKey(),
            'owner_scope' => 'superadmin',
            'template_key' => 'clinical_visit_summary',
            'action_key' => 'default',
            'name' => 'Resumen clinico',
            'blocks_json' => [
                ['type' => 'header', 'content' => '{{tenant.name}}'],
                ['type' => 'kv', 'items' => [['Paciente', '{{record.patient_name}}']]],
                ['type' => 'footer', 'content' => 'Emitido por {{tenant.slug}}'],
            ],
            'status' => 'published',
            'is_locked' => false,
        ]);

        $schema = [
            'fields' => [
                ['field_key' => 'patient_name'],
            ],
        ];

        $html = app(TemplateRenderService::class)->renderHtml($template, [
            'tenant' => [
                'name' => 'Clinica Central',
                'slug' => 'clinica-central',
            ],
            'record' => [
                'patient_name' => 'Maria Lopez',
            ],
        ], $schema);

        $this->assertStringContainsString('Clinica Central', $html);
        $this->assertStringContainsString('Maria Lopez', $html);
        $this->assertStringContainsString('Emitido por clinica-central', $html);
    }

    public function test_it_rejects_unsafe_placeholder_paths(): void
    {
        $template = DynamicPrintTemplate::create([
            'tenant_id' => null,
            'entity_definition_id' => null,
            'owner_scope' => 'superadmin',
            'template_key' => 'unsafe-template',
            'action_key' => 'default',
            'name' => 'Unsafe',
            'blocks_json' => [
                ['type' => 'paragraph', 'content' => '{{system.env}}'],
            ],
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $this->expectException(InvalidDynamicTemplateException::class);

        app(TemplateRenderService::class)->renderHtml($template, [
            'tenant' => ['name' => 'Demo'],
            'record' => [],
        ], []);
    }

    public function test_it_blocks_render_when_print_runtime_is_disabled(): void
    {
        config()->set('dynamic-runtime.print_runtime_enabled', false);

        $template = DynamicPrintTemplate::create([
            'tenant_id' => null,
            'entity_definition_id' => null,
            'owner_scope' => 'superadmin',
            'template_key' => 'disabled-print-runtime',
            'action_key' => 'default',
            'name' => 'Runtime disabled',
            'blocks_json' => [
                ['type' => 'paragraph', 'content' => 'Hola {{tenant.name}}'],
            ],
            'status' => 'draft',
            'is_locked' => false,
        ]);

        $this->expectException(InvalidDynamicTemplateException::class);

        app(TemplateRenderService::class)->renderHtml($template, [
            'tenant' => ['name' => 'Demo'],
            'record' => [],
        ], []);
    }
}
