<?php

declare(strict_types=1);

namespace Tests\Feature\DynamicForms;

use App\Services\DynamicForms\DynamicContractCompatibilityService;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;
use Tests\TestCase;

final class DynamicContractCompatibilityServiceTest extends TestCase
{
    public function test_schema_patch_rejects_structural_changes(): void
    {
        $service = app(DynamicContractCompatibilityService::class);

        $active = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'text', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.0.0',
        ];

        $candidate = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'text', 'storage_mode' => 'json'],
                ['field_key' => 'patient_age', 'field_type' => 'number', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.0.1',
        ];

        $this->expectException(InvalidDynamicTemplateException::class);

        $service->normalizeAndAssertSchema($candidate, $active);
    }

    public function test_schema_minor_allows_additive_changes(): void
    {
        $service = app(DynamicContractCompatibilityService::class);

        $active = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'text', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.0.0',
        ];

        $candidate = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'text', 'storage_mode' => 'json'],
                ['field_key' => 'patient_age', 'field_type' => 'number', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.1.0',
        ];

        $normalized = $service->normalizeAndAssertSchema($candidate, $active);

        $this->assertSame('1.1.0', $normalized['contract_semver']);
    }

    public function test_schema_minor_rejects_non_additive_changes(): void
    {
        $service = app(DynamicContractCompatibilityService::class);

        $active = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'text', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.0.0',
        ];

        $candidate = [
            'entity' => ['module_key' => 'operations', 'entity_key' => 'visit'],
            'fields' => [
                ['field_key' => 'patient_name', 'field_type' => 'textarea', 'storage_mode' => 'json'],
            ],
            'layouts' => [],
            'contract_semver' => '1.1.0',
        ];

        $this->expectException(InvalidDynamicTemplateException::class);

        $service->normalizeAndAssertSchema($candidate, $active);
    }

    public function test_template_minor_rejects_breaking_structure(): void
    {
        $service = app(DynamicContractCompatibilityService::class);

        $active = [
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'blocks_json' => [
                ['type' => 'header', 'content' => 'A'],
                ['type' => 'paragraph', 'content' => 'B'],
            ],
            'contract_semver' => '1.0.0',
        ];

        $candidate = [
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'blocks_json' => [
                ['type' => 'header', 'content' => 'A'],
                ['type' => 'table', 'source' => 'record.lines', 'columns' => ['name']],
            ],
            'contract_semver' => '1.1.0',
        ];

        $this->expectException(InvalidDynamicTemplateException::class);

        $service->normalizeAndAssertTemplate($candidate, $active);
    }

    public function test_template_patch_allows_non_structural_content_changes(): void
    {
        $service = app(DynamicContractCompatibilityService::class);

        $active = [
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'blocks_json' => [
                ['type' => 'header', 'content' => 'Clinica'],
                ['type' => 'paragraph', 'content' => 'Texto original'],
            ],
            'contract_semver' => '1.0.0',
        ];

        $candidate = [
            'template_key' => 'visit-summary',
            'action_key' => 'default',
            'blocks_json' => [
                ['type' => 'header', 'content' => 'Clinica Renovada'],
                ['type' => 'paragraph', 'content' => 'Texto corregido'],
            ],
            'contract_semver' => '1.0.1',
        ];

        $normalized = $service->normalizeAndAssertTemplate($candidate, $active);

        $this->assertSame('1.0.1', $normalized['contract_semver']);
    }
}

