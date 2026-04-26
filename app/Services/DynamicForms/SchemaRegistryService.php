<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicSchemaRegistryInterface;
use App\Application\DynamicForms\Contracts\DynamicContractCompatibilityInterface;
use App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface;
use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Events\DynamicForms\SchemaPublished;
use App\Models\Dynamic\EntityDefinition;
use App\Models\Dynamic\SchemaVersion;
use App\Models\User;
use App\Services\DynamicForms\Validation\SchemaContractValidator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class SchemaRegistryService implements DynamicSchemaRegistryInterface
{
    public function __construct(
        private readonly SchemaContractValidator $schemaContractValidator,
        private readonly DynamicContractCompatibilityInterface $contractCompatibility,
        private readonly DynamicCompiledCacheInterface $compiledCache,
        private readonly DynamicRuntimeFeatureManager $featureManager,
    ) {}

    /**
     * @throws ModelNotFoundException
     */
    public function resolveEntity(string $entityKey, ?string $tenantId = null): EntityDefinition
    {
        $query = EntityDefinition::query()
            ->where('entity_key', $entityKey)
            ->where('status', '!=', 'archived');

        if ($tenantId !== null && $tenantId !== '') {
            $query->where(function ($inner) use ($tenantId): void {
                $inner->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            })->orderByRaw('tenant_id IS NULL');
        } else {
            $query->whereNull('tenant_id');
        }

        $entity = $query->orderBy('updated_at', 'desc')->first();

        if (! $entity instanceof EntityDefinition) {
            throw (new ModelNotFoundException)->setModel(EntityDefinition::class, [$entityKey]);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActiveSchema(EntityDefinition $entity): array
    {
        return $this->getActiveSchemaData($entity)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildLiveSchema(EntityDefinition $entity): array
    {
        return $this->buildLiveSchemaData($entity)->toArray();
    }

    public function getActiveSchemaData(EntityDefinition $entity): DynamicSchemaData
    {
        $entity->loadMissing('activeSchemaVersion');

        if ($entity->activeSchemaVersion instanceof SchemaVersion) {
            $schema = $entity->activeSchemaVersion->schema_json;
            $normalized = is_array($schema) ? $schema : [];
            $versionNumber = (int) $entity->activeSchemaVersion->version_number;
            $tenantId = $entity->tenant_id ? (string) $entity->tenant_id : null;

            $normalized = $this->compiledCache->rememberSchema(
                tenantId: $tenantId,
                entityKey: (string) $entity->entity_key,
                schemaVersion: $versionNumber,
                resolver: static fn (): array => $normalized,
            );

            return DynamicSchemaData::fromArray($normalized);
        }

        return $this->buildLiveSchemaData($entity);
    }

    public function buildLiveSchemaData(EntityDefinition $entity): DynamicSchemaData
    {
        $entity->loadMissing(['fields', 'layouts']);

        return DynamicSchemaData::fromArray([
            'entity' => [
                'id' => (string) $entity->getKey(),
                'entity_key' => $entity->entity_key,
                'module_key' => $entity->module_key,
                'name' => $entity->name,
                'tenant_id' => $entity->tenant_id,
                'status' => $entity->status,
            ],
            'fields' => $entity->fields
                ->sortBy('sort_order')
                ->values()
                ->map(static fn ($field): array => [
                    'id' => (string) $field->getKey(),
                    'field_key' => $field->field_key,
                    'field_type' => $field->field_type,
                    'label' => $field->label,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'is_required' => (bool) $field->is_required,
                    'storage_mode' => $field->storage_mode,
                    'is_indexed' => (bool) $field->is_indexed,
                    'is_locked' => (bool) $field->is_locked,
                    'sort_order' => (int) $field->sort_order,
                    'default_value' => $field->default_value,
                    'validation_rules' => $field->validation_rules ?? [],
                    'options_json' => $field->options_json ?? [],
                    'visibility_json' => $field->visibility_json ?? [],
                    'conditions_json' => $field->conditions_json ?? [],
                    'hooks_json' => $field->hooks_json ?? [],
                    'status' => $field->status,
                ])->all(),
            'layouts' => $entity->layouts
                ->values()
                ->map(static fn ($layout): array => [
                    'id' => (string) $layout->getKey(),
                    'layout_key' => $layout->layout_key,
                    'layout_json' => $layout->layout_json,
                    'status' => $layout->status,
                ])->all(),
        ]);
    }

    public function publish(EntityDefinition $entity, ?User $publisher = null): SchemaVersion
    {
        if (! $this->featureManager->isEnabled() || ! $this->featureManager->isModuleEnabled($entity->module_key)) {
            throw new \RuntimeException("Dynamic schema publishing is disabled for module [{$entity->module_key}].");
        }

        return DB::transaction(function () use ($entity, $publisher): SchemaVersion {
            $schemaData = $this->buildLiveSchemaData($entity);
            $this->schemaContractValidator->validate($schemaData);

            $activeSchema = null;
            $entity->loadMissing('activeSchemaVersion');
            if ($entity->activeSchemaVersion instanceof SchemaVersion) {
                $activeSchema = is_array($entity->activeSchemaVersion->schema_json)
                    ? $entity->activeSchemaVersion->schema_json
                    : null;
            }

            $schema = $this->contractCompatibility->normalizeAndAssertSchema(
                $schemaData->toArray(),
                $activeSchema,
            );
            $lastVersion = $entity->schemaVersions()
                ->latest('version_number')
                ->first();

            $version = $entity->schemaVersions()->create([
                'version_number' => ($lastVersion?->version_number ?? 0) + 1,
                'status' => 'published',
                'schema_json' => $schema,
                'published_by' => $publisher?->getKey(),
                'published_at' => now(),
            ]);

            $entity->forceFill([
                'active_schema_version_id' => $version->getKey(),
                'status' => 'published',
            ])->save();

            event(new SchemaPublished(
                entityDefinitionId: (string) $entity->getKey(),
                entityKey: (string) $entity->entity_key,
                versionNumber: (int) $version->version_number,
                tenantId: $entity->tenant_id ? (string) $entity->tenant_id : null,
                publishedBy: $publisher?->getKey() ? (string) $publisher->getKey() : null,
            ));

            return $version;
        });
    }
}
