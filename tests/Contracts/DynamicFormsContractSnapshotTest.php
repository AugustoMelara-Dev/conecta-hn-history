<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

const DYNAMIC_FORMS_SNAPSHOT_ENV = 'DYNAMIC_FORMS_UPDATE_CONTRACT_SNAPSHOTS';

test('dynamic forms field/layout/template contracts match approved snapshots', function (): void {
    $snapshots = [
        'field-contract.v1.json' => [
            'field_key' => 'patient_allergies',
            'field_type' => 'textarea',
            'label' => 'Alergias',
            'placeholder' => 'Ingrese alergias conocidas',
            'help_text' => null,
            'is_required' => false,
            'storage_mode' => 'json',
            'is_indexed' => true,
            'is_locked' => false,
            'sort_order' => 10,
            'default_value' => null,
            'validation_rules' => ['max:1000'],
            'options_json' => [],
            'visibility_json' => ['roles' => ['owner', 'staff']],
            'conditions_json' => [],
            'hooks_json' => [],
            'status' => 'draft',
        ],
        'layout-contract.v1.json' => [
            'layout_key' => 'form.default',
            'layout_json' => [
                'field_order' => ['patient_allergies'],
                'sections' => [
                    [
                        'key' => 'clinical',
                        'title' => 'Datos clinicos',
                        'fields' => ['patient_allergies'],
                    ],
                ],
            ],
            'status' => 'draft',
        ],
        'template-contract.v1.json' => [
            'template_key' => 'clinical_visit_summary',
            'action_key' => 'default',
            'name' => 'Resumen clinico',
            'blocks_json' => [
                ['type' => 'header', 'content' => '{{tenant.name}}'],
                ['type' => 'kv', 'items' => [['Paciente', '{{record.patient_name}}']]],
                ['type' => 'table', 'source' => 'record.prescriptions', 'columns' => ['medicamento', 'dosis', 'frecuencia']],
                ['type' => 'footer', 'content' => '{{tenant.phone}}'],
            ],
            'placeholder_whitelist' => [
                'record.patient_name',
                'record.prescriptions',
                'tenant.name',
                'tenant.phone',
            ],
            'contract_family' => 'dynamic.template.clinical_visit_summary.default',
            'contract_semver' => '1.0.0',
            'placeholder_schema_version' => '1.0.0',
        ],
    ];

    $basePath = realpath(__DIR__.'/Schemas') ?: __DIR__.DIRECTORY_SEPARATOR.'Schemas';
    $basePath .= DIRECTORY_SEPARATOR.'DynamicForms';
    if (! is_dir($basePath)) {
        mkdir($basePath, 0755, true);
    }

    foreach ($snapshots as $fileName => $snapshotPayload) {
        $filePath = $basePath.DIRECTORY_SEPARATOR.$fileName;
        $canonicalCurrent = canonicalizeSnapshotPayload($snapshotPayload);

        if ((string) env(DYNAMIC_FORMS_SNAPSHOT_ENV, '0') === '1') {
            file_put_contents($filePath, json_encode($canonicalCurrent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        }

        Assert::assertFileExists(
            $filePath,
            "Missing dynamic forms contract snapshot [{$filePath}]. Set ".DYNAMIC_FORMS_SNAPSHOT_ENV."=1 and rerun to generate."
        );

        $existingRaw = file_get_contents($filePath);
        Assert::assertIsString($existingRaw);
        $existingPayload = json_decode($existingRaw, true, 512, JSON_THROW_ON_ERROR);
        $canonicalExisting = canonicalizeSnapshotPayload(is_array($existingPayload) ? $existingPayload : []);

        expect($canonicalCurrent)->toEqual(
            $canonicalExisting,
            "Dynamic forms contract drift detected in [{$fileName}]. "
            .'If intentional, rerun with '.DYNAMIC_FORMS_SNAPSHOT_ENV.'=1 and commit updated snapshots.'
        );
    }
});

/**
 * @param  array<string, mixed>|list<mixed>  $payload
 * @return array<string, mixed>|list<mixed>|scalar|null
 */
function canonicalizeSnapshotPayload(mixed $payload): mixed
{
    if (! is_array($payload)) {
        return $payload;
    }

    if (array_is_list($payload)) {
        return array_map(static fn (mixed $item): mixed => canonicalizeSnapshotPayload($item), $payload);
    }

    $canonical = [];
    ksort($payload);

    foreach ($payload as $key => $value) {
        $canonical[$key] = canonicalizeSnapshotPayload($value);
    }

    return $canonical;
}
