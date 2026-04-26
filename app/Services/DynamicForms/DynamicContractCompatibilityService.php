<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicContractCompatibilityInterface;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;

final class DynamicContractCompatibilityService implements DynamicContractCompatibilityInterface
{
    /**
     * @param  array<string, mixed>  $candidateSchema
     * @param  array<string, mixed>|null  $activeSchema
     * @return array<string, mixed>
     */
    public function normalizeAndAssertSchema(array $candidateSchema, ?array $activeSchema = null): array
    {
        $normalized = $this->normalizeSchemaContractMetadata($candidateSchema);
        $activeNormalized = is_array($activeSchema) ? $this->normalizeSchemaContractMetadata($activeSchema) : null;

        if ($activeNormalized === null) {
            return $normalized;
        }

        $this->assertSameContractFamily($normalized, $activeNormalized, 'schema');
        $this->assertSemverProgression($normalized, $activeNormalized, 'schema');

        $changes = $this->computeSchemaStructuralChanges($normalized, $activeNormalized);
        $this->assertChangesAllowedBySemver($changes, $normalized, $activeNormalized, 'schema');

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $candidateTemplate
     * @param  array<string, mixed>|null  $activeTemplate
     * @return array<string, mixed>
     */
    public function normalizeAndAssertTemplate(array $candidateTemplate, ?array $activeTemplate = null): array
    {
        $normalized = $this->normalizeTemplateContractMetadata($candidateTemplate);
        $activeNormalized = is_array($activeTemplate) ? $this->normalizeTemplateContractMetadata($activeTemplate) : null;

        if ($activeNormalized === null) {
            return $normalized;
        }

        $this->assertSameContractFamily($normalized, $activeNormalized, 'template');
        $this->assertSemverProgression($normalized, $activeNormalized, 'template');

        $changes = $this->computeTemplateStructuralChanges($normalized, $activeNormalized);
        $this->assertChangesAllowedBySemver($changes, $normalized, $activeNormalized, 'template');

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function normalizeSchemaContractMetadata(array $schema): array
    {
        $entity = is_array($schema['entity'] ?? null) ? $schema['entity'] : [];
        $moduleKey = (string) ($entity['module_key'] ?? 'unknown');
        $entityKey = (string) ($entity['entity_key'] ?? 'unknown');

        $contractFamily = (string) ($schema['contract_family'] ?? $entity['contract_family'] ?? "dynamic.schema.{$moduleKey}.{$entityKey}");
        $defaultSchemaSemver = (string) config('dynamic-forms.contract_defaults.schema.semver', '1.0.0');
        $defaultCompatibilityMode = (string) config('dynamic-forms.contract_defaults.schema.compatibility_mode', 'strict');
        $contractSemver = (string) ($schema['contract_semver'] ?? $entity['contract_semver'] ?? $defaultSchemaSemver);
        $compatibilityMode = (string) ($schema['compatibility_mode'] ?? $entity['compatibility_mode'] ?? $defaultCompatibilityMode);

        $this->assertValidSemver($contractSemver, 'schema');

        $schema['contract_family'] = $contractFamily;
        $schema['contract_semver'] = $contractSemver;
        $schema['compatibility_mode'] = $compatibilityMode;

        $entity['contract_family'] = $contractFamily;
        $entity['contract_semver'] = $contractSemver;
        $entity['compatibility_mode'] = $compatibilityMode;
        $schema['entity'] = $entity;

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function normalizeTemplateContractMetadata(array $template): array
    {
        $templateKey = (string) ($template['template_key'] ?? 'unknown');
        $actionKey = (string) ($template['action_key'] ?? 'default');

        $contractFamily = (string) ($template['contract_family'] ?? "dynamic.template.{$templateKey}.{$actionKey}");
        $defaultTemplateSemver = (string) config('dynamic-forms.contract_defaults.template.semver', '1.0.0');
        $defaultPlaceholderSchemaVersion = (string) config('dynamic-forms.contract_defaults.template.placeholder_schema_version', '1.0.0');
        $contractSemver = (string) ($template['contract_semver'] ?? $defaultTemplateSemver);
        $placeholderSchemaVersion = (string) ($template['placeholder_schema_version'] ?? $defaultPlaceholderSchemaVersion);

        $this->assertValidSemver($contractSemver, 'template');
        $this->assertValidSemver($placeholderSchemaVersion, 'template');

        $template['contract_family'] = $contractFamily;
        $template['contract_semver'] = $contractSemver;
        $template['placeholder_schema_version'] = $placeholderSchemaVersion;

        return $template;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $active
     */
    private function assertSameContractFamily(array $candidate, array $active, string $kind): void
    {
        $candidateFamily = (string) ($candidate['contract_family'] ?? '');
        $activeFamily = (string) ($active['contract_family'] ?? '');

        if ($candidateFamily !== $activeFamily) {
            throw new InvalidDynamicTemplateException(ucfirst($kind)." contract family mismatch [{$candidateFamily}] vs [{$activeFamily}].");
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $active
     */
    private function assertSemverProgression(array $candidate, array $active, string $kind): void
    {
        $candidateSemver = (string) ($candidate['contract_semver'] ?? '');
        $activeSemver = (string) ($active['contract_semver'] ?? '');

        if (version_compare($candidateSemver, $activeSemver, '<=')) {
            throw new InvalidDynamicTemplateException(ucfirst($kind)." semver must advance. Active [{$activeSemver}], candidate [{$candidateSemver}].");
        }
    }

    private function assertValidSemver(string $version, string $kind): void
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidDynamicTemplateException(ucfirst($kind)." semver [{$version}] is invalid. Expected MAJOR.MINOR.PATCH.");
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $active
     * @return array{added: int, removed: int, changed: int}
     */
    private function computeSchemaStructuralChanges(array $candidate, array $active): array
    {
        $candidateFields = $this->mapSchemaFieldsByKey($candidate);
        $activeFields = $this->mapSchemaFieldsByKey($active);

        $added = array_diff_key($candidateFields, $activeFields);
        $removed = array_diff_key($activeFields, $candidateFields);

        $changed = 0;
        foreach (array_intersect_key($candidateFields, $activeFields) as $fieldKey => $field) {
            if ($field !== $activeFields[$fieldKey]) {
                $changed++;
            }
        }

        return [
            'added' => count($added),
            'removed' => count($removed),
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    private function mapSchemaFieldsByKey(array $schema): array
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $mapped = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = (string) ($field['field_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $mapped[$key] = [
                'field_type' => (string) ($field['field_type'] ?? ''),
                'storage_mode' => (string) ($field['storage_mode'] ?? ''),
                'is_required' => (bool) ($field['is_required'] ?? false),
                'is_indexed' => (bool) ($field['is_indexed'] ?? false),
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $active
     * @return array{added: int, removed: int, changed: int}
     */
    private function computeTemplateStructuralChanges(array $candidate, array $active): array
    {
        $candidateBlocks = $this->normalizeTemplateStructure($candidate);
        $activeBlocks = $this->normalizeTemplateStructure($active);

        $candidateCount = count($candidateBlocks);
        $activeCount = count($activeBlocks);

        $added = max(0, $candidateCount - $activeCount);
        $removed = max(0, $activeCount - $candidateCount);
        $changed = 0;

        $size = min($candidateCount, $activeCount);
        for ($index = 0; $index < $size; $index++) {
            if ($candidateBlocks[$index] !== $activeBlocks[$index]) {
                $changed++;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTemplateStructure(array $template): array
    {
        $blocks = is_array($template['blocks_json'] ?? null) ? $template['blocks_json'] : [];
        $normalized = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $normalized[] = [
                'type' => (string) ($block['type'] ?? ''),
                'source' => (string) ($block['source'] ?? ''),
                'columns' => array_values(array_map('strval', is_array($block['columns'] ?? null) ? $block['columns'] : [])),
                'item_keys' => $this->extractKvItemKeys($block),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, string>
     */
    private function extractKvItemKeys(array $block): array
    {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];
        $keys = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $keys[] = (string) ($item[0] ?? '');
        }

        return $keys;
    }

    /**
     * @param  array{added: int, removed: int, changed: int}  $changes
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $active
     */
    private function assertChangesAllowedBySemver(array $changes, array $candidate, array $active, string $kind): void
    {
        $candidateSemver = (string) ($candidate['contract_semver'] ?? '');
        $activeSemver = (string) ($active['contract_semver'] ?? '');

        [$candidateMajor, $candidateMinor] = $this->majorMinor($candidateSemver);
        [$activeMajor, $activeMinor] = $this->majorMinor($activeSemver);

        $hasStructuralChange = $changes['added'] > 0 || $changes['removed'] > 0 || $changes['changed'] > 0;

        if ($candidateMajor > $activeMajor) {
            return;
        }

        if ($candidateMajor === $activeMajor && $candidateMinor > $activeMinor) {
            if ($changes['removed'] > 0 || $changes['changed'] > 0) {
                throw new InvalidDynamicTemplateException(ucfirst($kind)." MINOR can only be additive. Remove/Change detected.");
            }

            return;
        }

        if ($hasStructuralChange) {
            throw new InvalidDynamicTemplateException(ucfirst($kind)." PATCH cannot include structural changes.");
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function majorMinor(string $semver): array
    {
        $parts = explode('.', $semver);
        $major = isset($parts[0]) ? (int) $parts[0] : 0;
        $minor = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$major, $minor];
    }
}
