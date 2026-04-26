<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicRecordPersistenceInterface;
use App\Application\DynamicForms\Data\DynamicSchemaData;
use App\Events\DynamicForms\DynamicRecordPersisted;
use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\DynamicRecordIndexValue;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RecordPersistenceService implements DynamicRecordPersistenceInterface
{
    public function __construct(
        private readonly SchemaRegistryService $schemaRegistry,
        private readonly FormCompilerService $formCompiler,
        private readonly DynamicRuntimeFeatureManager $featureManager,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $coreAttributes
     *
     * @throws ValidationException
     */
    public function upsert(
        EntityDefinition $entityDefinition,
        array $payload,
        array $coreAttributes = [],
        ?DynamicRecord $record = null
    ): DynamicRecord {
        if (! $this->featureManager->isRecordRuntimeEnabled() || ! $this->featureManager->isModuleEnabled($entityDefinition->module_key)) {
            throw ValidationException::withMessages([
                'runtime' => "Dynamic record runtime is disabled for module [{$entityDefinition->module_key}].",
            ]);
        }

        $tenantId = (string) ($coreAttributes['tenant_id'] ?? $record?->tenant_id ?? '');
        if ($tenantId === '') {
            throw ValidationException::withMessages([
                'tenant_id' => 'tenant_id is required for dynamic record persistence.',
            ]);
        }

        if ($entityDefinition->tenant_id !== null && (string) $entityDefinition->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Entity definition does not belong to the provided tenant.',
            ]);
        }

        if ($record instanceof DynamicRecord) {
            if ((string) $record->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'Cross-tenant update attempt detected for dynamic record.',
                ]);
            }

            if ((string) $record->entity_definition_id !== (string) $entityDefinition->getKey()) {
                throw ValidationException::withMessages([
                    'entity_definition_id' => 'Dynamic record cannot be moved across entity definitions.',
                ]);
            }
        }

        $schema = $this->schemaRegistry->getActiveSchemaData($entityDefinition);
        $rules = $this->formCompiler->compileValidationRulesFromData($schema, $payload);
        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($entityDefinition, $payload, $coreAttributes, $record, $schema, $tenantId): DynamicRecord {
            $record ??= new DynamicRecord;

            $record->forceFill([
                'tenant_id' => $tenantId,
                'location_id' => $coreAttributes['location_id'] ?? $record->location_id,
                'owner_id' => $coreAttributes['owner_id'] ?? $record->owner_id,
                'status' => $coreAttributes['status'] ?? $record->status ?? config('dynamic-forms.default_record_status', 'active'),
                'entity_definition_id' => $entityDefinition->getKey(),
                'schema_version_id' => $entityDefinition->active_schema_version_id,
                'payload_json' => $payload,
            ])->save();

            $this->syncIndexedValues($record, $schema, $payload);

            event(new DynamicRecordPersisted(
                recordId: (string) $record->getKey(),
                entityDefinitionId: (string) $entityDefinition->getKey(),
                tenantId: (string) $record->tenant_id,
                operation: $record->wasRecentlyCreated ? 'created' : 'updated',
            ));

            return $record->fresh(['entityDefinition', 'schemaVersion', 'indexValues']) ?? $record;
        });
    }

    /**
     * Rebuild indexed values from stored payload without mutating record payload/history.
     *
     * @throws ValidationException
     */
    public function resyncIndexesForRecord(EntityDefinition $entityDefinition, DynamicRecord $record): void
    {
        if ((string) $record->entity_definition_id !== (string) $entityDefinition->getKey()) {
            throw ValidationException::withMessages([
                'entity_definition_id' => 'Record does not belong to the provided entity definition.',
            ]);
        }

        $schema = $this->schemaRegistry->getActiveSchemaData($entityDefinition);
        $payload = is_array($record->payload_json) ? $record->payload_json : [];

        DB::transaction(function () use ($record, $schema, $payload): void {
            $this->syncIndexedValues($record, $schema, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     */
    private function syncIndexedValues(DynamicRecord $record, DynamicSchemaData $schemaData, array $payload): void
    {
        $indexedFields = collect($schemaData->fields)
            ->filter(static function ($field): bool {
                if (! is_array($field)) {
                    return false;
                }

                return (bool) ($field['is_indexed'] ?? false)
                    || (($field['storage_mode'] ?? null) === 'indexed');
            })
            ->map(static fn (array $field): string => (string) ($field['field_key'] ?? ''))
            ->filter(static fn (string $fieldKey): bool => $fieldKey !== '')
            ->values();

        if ($indexedFields->isEmpty()) {
            $record->indexValues()->delete();

            return;
        }

        /** @var array<int, string> $indexedFieldKeys */
        $indexedFieldKeys = $indexedFields->all();

        $record->indexValues()
            ->whereNotIn('field_key', $indexedFieldKeys)
            ->delete();

        foreach ($indexedFieldKeys as $fieldKey) {
            $raw = data_get($payload, $fieldKey);

            if (is_array($raw) || is_object($raw)) {
                $raw = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DynamicRecordIndexValue::updateOrCreate(
                [
                    'dynamic_record_id' => $record->getKey(),
                    'field_key' => $fieldKey,
                ],
                [
                    'tenant_id' => $record->tenant_id,
                    'entity_definition_id' => $record->entity_definition_id,
                    'value_string' => $this->asString($raw),
                    'value_number' => $this->asNumber($raw),
                    'value_date' => $this->asDate($raw),
                    'value_boolean' => $this->asBoolean($raw),
                ]
            );
        }
    }

    private function asString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, 255);
    }

    private function asNumber(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function asDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function asBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }

        return null;
    }
}
