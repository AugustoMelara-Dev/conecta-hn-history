<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Application\DynamicForms\Contracts\DynamicSearchInterface;
use App\Models\Dynamic\DynamicRecord;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class DynamicSearchService implements DynamicSearchInterface
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(
        EntityDefinition $entityDefinition,
        string $tenantId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        if (! $this->featureManager->isRecordRuntimeEnabled() || ! $this->featureManager->isModuleEnabled($entityDefinition->module_key)) {
            throw ValidationException::withMessages([
                'runtime' => "Dynamic search runtime is disabled for module [{$entityDefinition->module_key}].",
            ]);
        }

        $query = DynamicRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_definition_id', $entityDefinition->getKey())
            ->latest('created_at');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (in_array($field, ['status', 'owner_id', 'location_id'], true)) {
                $query->where($field, $value);
                continue;
            }

            $query->whereHas('indexValues', function (Builder $indexQuery) use ($field, $value): void {
                $indexQuery->where('field_key', $field);

                if (is_bool($value)) {
                    $indexQuery->where('value_boolean', $value);

                    return;
                }

                if (is_numeric($value)) {
                    $indexQuery->where('value_number', (float) $value);

                    return;
                }

                $indexQuery->where('value_string', 'like', '%'.mb_substr((string) $value, 0, 255).'%');
            });
        }

        return $query;
    }
}
