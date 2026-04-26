<?php

declare(strict_types=1);

namespace App\Application\DynamicForms\Data;

final readonly class DynamicTemplateData
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>  $placeholderWhitelist
     */
    public function __construct(
        public string $templateKey,
        public string $actionKey,
        public string $name,
        public array $blocks,
        public array $placeholderWhitelist = [],
        public string $contractFamily = 'dynamic.template.unknown.default',
        public string $contractSemver = '1.0.0',
        public string $placeholderSchemaVersion = '1.0.0',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $templateKey = (string) ($payload['template_key'] ?? '');
        $actionKey = (string) ($payload['action_key'] ?? 'default');

        $defaultSemver = (string) config('dynamic-forms.contract_defaults.template.semver', '1.0.0');
        $defaultPlaceholderVersion = (string) config('dynamic-forms.contract_defaults.template.placeholder_schema_version', '1.0.0');

        return new self(
            templateKey: $templateKey,
            actionKey: $actionKey,
            name: (string) ($payload['name'] ?? ''),
            blocks: is_array($payload['blocks_json'] ?? null) ? array_values($payload['blocks_json']) : [],
            placeholderWhitelist: is_array($payload['placeholder_whitelist'] ?? null)
                ? array_values(array_filter($payload['placeholder_whitelist'], 'is_string'))
                : [],
            contractFamily: (string) ($payload['contract_family'] ?? "dynamic.template.{$templateKey}.{$actionKey}"),
            contractSemver: (string) ($payload['contract_semver'] ?? $defaultSemver),
            placeholderSchemaVersion: (string) ($payload['placeholder_schema_version'] ?? $defaultPlaceholderVersion),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'template_key' => $this->templateKey,
            'action_key' => $this->actionKey,
            'name' => $this->name,
            'blocks_json' => $this->blocks,
            'placeholder_whitelist' => $this->placeholderWhitelist,
            'contract_family' => $this->contractFamily,
            'contract_semver' => $this->contractSemver,
            'placeholder_schema_version' => $this->placeholderSchemaVersion,
        ];
    }
}
