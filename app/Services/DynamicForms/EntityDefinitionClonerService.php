<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Events\DynamicForms\TenantBlueprintCloned;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EntityDefinitionClonerService
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
    ) {}

    /**
     * @throws ValidationException
     */
    public function cloneForTenant(EntityDefinition $source, string $tenantId, ?string $actorId = null): EntityDefinition
    {
        if (! $this->featureManager->isTenantConfiguratorEnabled() || ! $this->featureManager->isModuleEnabled($source->module_key)) {
            throw ValidationException::withMessages([
                'runtime' => "Dynamic tenant configurator is disabled for module [{$source->module_key}].",
            ]);
        }

        if ($source->tenant_id !== null) {
            throw ValidationException::withMessages([
                'entity' => 'Solo se pueden clonar blueprints globales de superadmin.',
            ]);
        }

        if (! $source->allow_tenant_overrides) {
            throw ValidationException::withMessages([
                'entity' => 'Este blueprint no permite overrides por tenant.',
            ]);
        }

        $exists = EntityDefinition::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_key', $source->entity_key)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'entity' => 'Ya existe una definicion de esta entidad para este tenant.',
            ]);
        }

        return DB::transaction(function () use ($source, $tenantId, $actorId): EntityDefinition {
            $source->loadMissing(['fields', 'layouts']);

            $cloned = EntityDefinition::create([
                'tenant_id' => $tenantId,
                'source_entity_definition_id' => $source->getKey(),
                'owner_scope' => 'tenant',
                'module_key' => $source->module_key,
                'entity_key' => $source->entity_key,
                'name' => $source->name,
                'business_segment_key' => $source->business_segment_key,
                'description' => $source->description,
                'status' => 'draft',
                'allow_tenant_overrides' => true,
                'is_locked' => false,
            ]);

            foreach ($source->fields as $field) {
                $cloned->fields()->create([
                    'field_key' => $field->field_key,
                    'field_type' => $field->field_type,
                    'label' => $field->label,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'is_required' => (bool) $field->is_required,
                    'storage_mode' => $field->storage_mode,
                    'is_indexed' => (bool) $field->is_indexed,
                    'is_locked' => false,
                    'sort_order' => (int) $field->sort_order,
                    'default_value' => $field->default_value,
                    'validation_rules' => $field->validation_rules,
                    'options_json' => $field->options_json,
                    'visibility_json' => $field->visibility_json,
                    'conditions_json' => $field->conditions_json,
                    'hooks_json' => $field->hooks_json,
                    'status' => 'draft',
                ]);
            }

            foreach ($source->layouts as $layout) {
                $cloned->layouts()->create([
                    'layout_key' => $layout->layout_key,
                    'layout_json' => $layout->layout_json,
                    'status' => 'draft',
                ]);
            }

            event(new TenantBlueprintCloned(
                blueprintType: 'entity_definition',
                sourceId: (string) $source->getKey(),
                targetId: (string) $cloned->getKey(),
                tenantId: $tenantId,
                actorId: $actorId,
            ));

            return $cloned;
        });
    }
}
