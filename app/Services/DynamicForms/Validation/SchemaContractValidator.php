<?php

declare(strict_types=1);

namespace App\Services\DynamicForms\Validation;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Services\DynamicForms\Exceptions\InvalidDynamicTemplateException;

final class SchemaContractValidator
{
    public function __construct(
        private readonly FieldHookPolicyValidator $hookValidator
    ) {}

    public function validate(DynamicSchemaData $schemaData): void
    {
        $entityKey = (string) ($schemaData->entity['entity_key'] ?? '');
        if ($entityKey === '') {
            throw new InvalidDynamicTemplateException('Schema entity.entity_key is required.');
        }

        if ($schemaData->fields === []) {
            throw new InvalidDynamicTemplateException("Schema [{$entityKey}] must define at least one field.");
        }

        $seenFieldKeys = [];

        foreach ($schemaData->fields as $field) {
            if (! is_array($field)) {
                throw new InvalidDynamicTemplateException("Schema [{$entityKey}] contains an invalid field payload.");
            }

            $fieldKey = (string) ($field['field_key'] ?? '');
            $fieldType = (string) ($field['field_type'] ?? '');
            $storageMode = (string) ($field['storage_mode'] ?? 'json');

            if ($fieldKey === '') {
                throw new InvalidDynamicTemplateException("Schema [{$entityKey}] contains a field without field_key.");
            }

            if (isset($seenFieldKeys[$fieldKey])) {
                throw new InvalidDynamicTemplateException("Schema [{$entityKey}] has duplicate field_key [{$fieldKey}].");
            }

            $seenFieldKeys[$fieldKey] = true;

            $allowedTypes = config('dynamic-forms.field_types', []);
            if (! is_array($allowedTypes) || ! in_array($fieldType, $allowedTypes, true)) {
                throw new InvalidDynamicTemplateException("Field [{$fieldKey}] has unsupported type [{$fieldType}].");
            }

            $allowedStorageModes = config('dynamic-forms.storage_modes', []);
            if (! is_array($allowedStorageModes) || ! in_array($storageMode, $allowedStorageModes, true)) {
                throw new InvalidDynamicTemplateException("Field [{$fieldKey}] has unsupported storage_mode [{$storageMode}].");
            }

            $this->hookValidator->validate($field);
        }

        foreach ($schemaData->layouts as $layout) {
            if (! is_array($layout)) {
                throw new InvalidDynamicTemplateException("Schema [{$entityKey}] contains an invalid layout payload.");
            }

            $layoutKey = (string) ($layout['layout_key'] ?? '');
            if ($layoutKey === '') {
                throw new InvalidDynamicTemplateException("Schema [{$entityKey}] contains a layout without layout_key.");
            }
        }
    }
}

