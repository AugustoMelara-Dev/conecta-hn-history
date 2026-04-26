<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Data;

final readonly class DynamicSchemaData
{
    /**
     * @param  array<string, mixed>  $entity
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $layouts
     */
    public function __construct(
        public array $entity,
        public array $fields,
        public array $layouts,
        public string $contractFamily = 'dynamic.schema.unknown.unknown',
        public string $contractSemver = '1.0.0',
        public string $compatibilityMode = 'strict',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $moduleKey = (string) ($entity['module_key'] ?? 'unknown');
        $entityKey = (string) ($entity['entity_key'] ?? 'unknown');

        $defaultSemver = (string) config('dynamic-forms.contract_defaults.schema.semver', '1.0.0');
        $defaultCompatibilityMode = (string) config('dynamic-forms.contract_defaults.schema.compatibility_mode', 'strict');

        return new self(
            entity: $entity,
            fields: is_array($payload['fields'] ?? null) ? array_values($payload['fields']) : [],
            layouts: is_array($payload['layouts'] ?? null) ? array_values($payload['layouts']) : [],
            contractFamily: (string) ($payload['contract_family'] ?? $entity['contract_family'] ?? "dynamic.schema.{$moduleKey}.{$entityKey}"),
            contractSemver: (string) ($payload['contract_semver'] ?? $entity['contract_semver'] ?? $defaultSemver),
            compatibilityMode: (string) ($payload['compatibility_mode'] ?? $entity['compatibility_mode'] ?? $defaultCompatibilityMode),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'fields' => $this->fields,
            'layouts' => $this->layouts,
            'contract_family' => $this->contractFamily,
            'contract_semver' => $this->contractSemver,
            'compatibility_mode' => $this->compatibilityMode,
        ];
    }
}
