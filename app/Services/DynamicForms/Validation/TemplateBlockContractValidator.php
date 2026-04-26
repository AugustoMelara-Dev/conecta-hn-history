<?php

declare(strict_types=1);

namespace App\Services\DynamicForms\Validation;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Application\DynamicForms\Data\DynamicTemplateData;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;

final class TemplateBlockContractValidator
{
    public function validate(DynamicTemplateData $templateData, DynamicSchemaData $schemaData): void
    {
        if ($templateData->templateKey === '') {
            throw new InvalidDynamicTemplateException('Template template_key is required.');
        }

        if ($templateData->blocks === []) {
            throw new InvalidDynamicTemplateException("Template [{$templateData->templateKey}] must contain blocks.");
        }

        $allowedBlockTypes = config('dynamic-forms.allowed_template_blocks', []);
        if (! is_array($allowedBlockTypes)) {
            $allowedBlockTypes = [];
        }

        $allowedPaths = $this->buildAllowedPaths($schemaData, $templateData);

        foreach ($templateData->blocks as $index => $block) {
            if (! is_array($block)) {
                throw new InvalidDynamicTemplateException("Template block #{$index} has invalid payload.");
            }

            $type = (string) ($block['type'] ?? '');
            if ($type === '' || ! in_array($type, $allowedBlockTypes, true)) {
                throw new InvalidDynamicTemplateException("Template block type [{$type}] is not allowed.");
            }

            if ($type === 'table') {
                $source = (string) ($block['source'] ?? '');
                if (! str_starts_with($source, 'record.') || ! in_array($source, $allowedPaths, true)) {
                    throw new InvalidDynamicTemplateException("Template table source [{$source}] is invalid.");
                }
            }

            $this->validatePlaceholders($block, $allowedPaths);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<int, string>  $allowedPaths
     */
    private function validatePlaceholders(array $value, array $allowedPaths): void
    {
        array_walk_recursive($value, function (mixed $node) use ($allowedPaths): void {
            if (! is_string($node) || ! str_contains($node, '{{')) {
                return;
            }

            preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $node, $matches);
            $paths = $matches[1] ?? [];
            if (! is_array($paths)) {
                return;
            }

            foreach ($paths as $path) {
                $normalized = (string) $path;
                if ($normalized === '') {
                    continue;
                }

                if (! in_array($normalized, $allowedPaths, true)) {
                    throw new InvalidDynamicTemplateException("Placeholder [{$normalized}] is not allowed in template.");
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function buildAllowedPaths(DynamicSchemaData $schemaData, DynamicTemplateData $templateData): array
    {
        $paths = [
            'tenant.name',
            'tenant.slug',
            'tenant.phone',
            'tenant.email',
            'tenant.address',
            'record.id',
            'record.status',
            'record.created_at',
            'record.updated_at',
        ];

        foreach ($schemaData->fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = (string) ($field['field_key'] ?? '');
            if ($fieldKey !== '') {
                $paths[] = "record.{$fieldKey}";
            }
        }

        foreach ($templateData->placeholderWhitelist as $path) {
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}

