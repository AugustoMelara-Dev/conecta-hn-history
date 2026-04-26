<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Application\DynamicForms\Data\DynamicTemplateData;
use App\Application\DynamicForms\Contracts\DynamicContractCompatibilityInterface;
use App\Events\DynamicForms\TemplatePublished;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\DynamicPrintTemplateVersion;
use App\Models\User;
use App\Services\DynamicForms\Validation\TemplateBlockContractValidator;
use Illuminate\Support\Facades\DB;

final class TemplateVersioningService
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
        private readonly SchemaRegistryService $schemaRegistry,
        private readonly TemplateBlockContractValidator $templateValidator,
        private readonly DynamicContractCompatibilityInterface $contractCompatibility,
    ) {}

    /**
     * Publish the current template payload as a stable version.
     */
    public function publish(DynamicPrintTemplate $template, ?User $publisher = null): DynamicPrintTemplateVersion
    {
        $template->loadMissing('entityDefinition');

        $moduleKey = $template->entityDefinition?->module_key;
        if (! $this->featureManager->isPrintRuntimeEnabled() || ! $this->featureManager->isModuleEnabled($moduleKey)) {
            throw new \RuntimeException("Dynamic print publishing is disabled for module [{$moduleKey}].");
        }

        $schemaData = $this->resolveSchemaData($template);
        $templateData = DynamicTemplateData::fromArray([
            'template_key' => $template->template_key,
            'action_key' => $template->action_key,
            'name' => $template->name,
            'blocks_json' => is_array($template->blocks_json) ? $template->blocks_json : [],
            'placeholder_whitelist' => is_array($template->placeholder_whitelist) ? $template->placeholder_whitelist : [],
        ]);

        $this->templateValidator->validate($templateData, $schemaData);

        $activeTemplate = null;
        $template->loadMissing('activeVersion');
        if ($template->activeVersion instanceof DynamicPrintTemplateVersion) {
            $activeTemplate = [
                'template_key' => (string) $template->template_key,
                'action_key' => (string) $template->action_key,
                'blocks_json' => is_array($template->activeVersion->blocks_json) ? $template->activeVersion->blocks_json : [],
                'placeholder_whitelist' => is_array($template->placeholder_whitelist) ? $template->placeholder_whitelist : [],
                'contract_family' => (string) data_get($template->activeVersion->metadata_json, 'contract_family', ''),
                'contract_semver' => (string) data_get(
                    $template->activeVersion->metadata_json,
                    'contract_semver',
                    config('dynamic-forms.contract_defaults.template.semver', '1.0.0')
                ),
                'placeholder_schema_version' => (string) data_get(
                    $template->activeVersion->metadata_json,
                    'placeholder_schema_version',
                    config('dynamic-forms.contract_defaults.template.placeholder_schema_version', '1.0.0')
                ),
            ];
        }

        $normalizedTemplate = $this->contractCompatibility->normalizeAndAssertTemplate(
            $templateData->toArray(),
            $activeTemplate,
        );

        return DB::transaction(function () use ($template, $publisher, $normalizedTemplate): DynamicPrintTemplateVersion {
            $lastVersion = $template->versions()->latest('version_number')->first();

            $version = $template->versions()->create([
                'version_number' => ($lastVersion?->version_number ?? 0) + 1,
                'status' => 'published',
                'blocks_json' => $template->blocks_json ?? [],
                'metadata_json' => [
                    'template_key' => $template->template_key,
                    'action_key' => $template->action_key,
                    'name' => $template->name,
                    'description' => $template->description,
                    'contract_family' => data_get($normalizedTemplate, 'contract_family'),
                    'contract_semver' => data_get($normalizedTemplate, 'contract_semver'),
                    'placeholder_schema_version' => data_get($normalizedTemplate, 'placeholder_schema_version'),
                ],
                'published_by' => $publisher?->getKey(),
                'published_at' => now(),
            ]);

            $template->forceFill([
                'status' => 'published',
                'active_version_id' => $version->getKey(),
            ])->save();

            event(new TemplatePublished(
                templateId: (string) $template->getKey(),
                templateKey: (string) $template->template_key,
                versionNumber: (int) $version->version_number,
                tenantId: $template->tenant_id ? (string) $template->tenant_id : null,
                publishedBy: $publisher?->getKey() ? (string) $publisher->getKey() : null,
            ));

            return $version;
        });
    }

    private function resolveSchemaData(DynamicPrintTemplate $template): DynamicSchemaData
    {
        if ($template->entityDefinition !== null) {
            return $this->schemaRegistry->getActiveSchemaData($template->entityDefinition);
        }

        return DynamicSchemaData::fromArray([
            'entity' => [
                'entity_key' => 'global_template',
                'module_key' => 'operations',
            ],
            'fields' => [],
            'layouts' => [],
        ]);
    }
}
