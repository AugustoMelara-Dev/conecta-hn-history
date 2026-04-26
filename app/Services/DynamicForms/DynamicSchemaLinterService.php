<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicSchemaLinterInterface;
use App\Application\DynamicForms\Data\DynamicTemplateData;
use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;
use App\Services\DynamicForms\Validation\FieldHookPolicyValidator;
use App\Services\DynamicForms\Validation\TemplateBlockContractValidator;
use Illuminate\Support\Arr;

final class DynamicSchemaLinterService implements DynamicSchemaLinterInterface
{
    public function __construct(
        private readonly SchemaRegistryService $schemaRegistry,
        private readonly FieldHookPolicyValidator $hookPolicyValidator,
        private readonly TemplateBlockContractValidator $templateValidator,
    ) {}

    /**
     * @return array{errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,meta:array<string,mixed>}
     */
    public function lint(bool $strict = false, bool $changedOnly = false): array
    {
        $errors = [];
        $warnings = [];

        $entitiesQuery = EntityDefinition::query()->with(['fields', 'layouts', 'activeSchemaVersion']);
        if ($changedOnly) {
            $entitiesQuery->where('updated_at', '>=', now()->subDay());
        }

        $entities = $entitiesQuery->get();

        foreach ($entities as $entity) {
            $schemaData = $this->schemaRegistry->buildLiveSchemaData($entity);
            $fieldsByKey = [];
            foreach ($schemaData->fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldKey = (string) ($field['field_key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $fieldsByKey[$fieldKey] = $field;

                try {
                    $this->hookPolicyValidator->validate($field);
                } catch (InvalidDynamicTemplateException $exception) {
                    $errors[] = $this->error(
                        category: 'hook_policy',
                        reference: "entity:{$entity->entity_key}.field:{$fieldKey}",
                        message: $exception->getMessage(),
                    );
                }

                $ruleIssues = $this->lintValidationRulesByType((string) ($field['field_type'] ?? ''), $field);
                foreach ($ruleIssues as $issue) {
                    $errors[] = $this->error(
                        category: 'validation_rule_type_mismatch',
                        reference: "entity:{$entity->entity_key}.field:{$fieldKey}",
                        message: $issue,
                    );
                }
            }

            foreach ($schemaData->layouts as $layout) {
                if (! is_array($layout)) {
                    continue;
                }

                $layoutKey = (string) ($layout['layout_key'] ?? 'unknown');
                $layoutJson = is_array($layout['layout_json'] ?? null) ? $layout['layout_json'] : [];
                $referencedKeys = $this->extractLayoutFieldKeys($layoutJson);

                foreach ($referencedKeys as $fieldKey) {
                    if (! array_key_exists($fieldKey, $fieldsByKey)) {
                        $errors[] = $this->error(
                            category: 'orphan_layout_key',
                            reference: "entity:{$entity->entity_key}.layout:{$layoutKey}",
                            message: "Field key [{$fieldKey}] is referenced in layout but missing in field definitions.",
                        );
                    }
                }
            }

            if ($entity->status === 'published' && $entity->active_schema_version_id === null) {
                $errors[] = $this->error(
                    category: 'status_inconsistency',
                    reference: "entity:{$entity->entity_key}",
                    message: 'Published entity has no active schema version.',
                );
            }
        }

        $templatesQuery = DynamicPrintTemplate::query()->with(['entityDefinition', 'activeVersion']);
        if ($changedOnly) {
            $templatesQuery->where('updated_at', '>=', now()->subDay());
        }

        $templates = $templatesQuery->get();

        foreach ($templates as $template) {
            $schemaData = $template->entityDefinition
                ? $this->schemaRegistry->getActiveSchemaData($template->entityDefinition)
                : DynamicSchemaData::fromArray([
                    'entity' => [
                        'entity_key' => 'global_template',
                        'module_key' => 'operations',
                    ],
                    'fields' => [],
                    'layouts' => [],
                ]);

            try {
                $this->templateValidator->validate(
                    DynamicTemplateData::fromArray([
                        'template_key' => $template->template_key,
                        'action_key' => $template->action_key,
                        'name' => $template->name,
                        'blocks_json' => is_array($template->blocks_json) ? $template->blocks_json : [],
                        'placeholder_whitelist' => is_array($template->placeholder_whitelist) ? $template->placeholder_whitelist : [],
                        'contract_family' => data_get($template->activeVersion?->metadata_json, 'contract_family'),
                        'contract_semver' => data_get($template->activeVersion?->metadata_json, 'contract_semver'),
                        'placeholder_schema_version' => data_get($template->activeVersion?->metadata_json, 'placeholder_schema_version'),
                    ]),
                    $schemaData,
                );
            } catch (InvalidDynamicTemplateException $exception) {
                $errors[] = $this->error(
                    category: 'template_contract',
                    reference: "template:{$template->template_key}",
                    message: $exception->getMessage(),
                );
            }

            if ($template->status === 'published' && $template->active_version_id === null) {
                $errors[] = $this->error(
                    category: 'status_inconsistency',
                    reference: "template:{$template->template_key}",
                    message: 'Published template has no active version.',
                );
            }
        }

        if (! $strict && $errors !== []) {
            foreach ($errors as $error) {
                $warnings[] = $error;
            }

            $errors = [];
        }

        return [
            'errors' => array_values($errors),
            'warnings' => array_values($warnings),
            'meta' => [
                'strict' => $strict,
                'changed_only' => $changedOnly,
                'entities_scanned' => $entities->count(),
                'templates_scanned' => $templates->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function lintValidationRulesByType(string $fieldType, array $field): array
    {
        $rules = Arr::wrap($field['validation_rules'] ?? []);
        $issues = [];
        $ruleText = implode('|', array_map(static fn (mixed $rule): string => (string) $rule, $rules));

        if (in_array($fieldType, ['number', 'money'], true) && str_contains($ruleText, 'email')) {
            $issues[] = 'Numeric field contains email validation rule.';
        }

        if (in_array($fieldType, ['date', 'datetime'], true) && str_contains($ruleText, 'numeric')) {
            $issues[] = 'Date field contains numeric validation rule.';
        }

        if (in_array($fieldType, ['boolean'], true) && str_contains($ruleText, 'max:')) {
            $issues[] = 'Boolean field should not use max:* rules.';
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $layoutJson
     * @return array<int, string>
     */
    private function extractLayoutFieldKeys(array $layoutJson): array
    {
        $keys = [];

        foreach (Arr::wrap($layoutJson['field_order'] ?? []) as $fieldKey) {
            if (is_string($fieldKey) && $fieldKey !== '') {
                $keys[] = $fieldKey;
            }
        }

        foreach (Arr::wrap($layoutJson['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach (Arr::wrap($section['fields'] ?? []) as $fieldKey) {
                if (is_string($fieldKey) && $fieldKey !== '') {
                    $keys[] = $fieldKey;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array{category:string,reference:string,message:string}
     */
    private function error(string $category, string $reference, string $message): array
    {
        return [
            'category' => $category,
            'reference' => $reference,
            'message' => $message,
        ];
    }
}
