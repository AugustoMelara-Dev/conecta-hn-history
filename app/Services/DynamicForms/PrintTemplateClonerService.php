<?php

declare(strict_types=1);

namespace App\Services\DynamicForms;

use App\Events\DynamicForms\TenantBlueprintCloned;
use App\Models\Dynamic\DynamicPrintTemplate;
use App\Models\Dynamic\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrintTemplateClonerService
{
    public function __construct(
        private readonly DynamicRuntimeFeatureManager $featureManager,
    ) {}

    /**
     * @throws ValidationException
     */
    public function cloneForTenant(DynamicPrintTemplate $source, string $tenantId, ?string $userId = null): DynamicPrintTemplate
    {
        $source->loadMissing('entityDefinition');

        if (! $this->featureManager->isTenantConfiguratorEnabled() || ! $this->featureManager->isModuleEnabled($source->entityDefinition?->module_key)) {
            throw ValidationException::withMessages([
                'runtime' => 'Dynamic tenant print template configurator is disabled for this module.',
            ]);
        }

        if ($source->tenant_id !== null) {
            throw ValidationException::withMessages([
                'template' => 'Solo se pueden clonar plantillas globales de superadmin.',
            ]);
        }

        $exists = DynamicPrintTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('template_key', $source->template_key)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'template' => 'Ya existe una plantilla con este template_key en este tenant.',
            ]);
        }

        return DB::transaction(function () use ($source, $tenantId, $userId): DynamicPrintTemplate {
            $targetEntityDefinitionId = $this->resolveTenantEntityDefinitionId($source, $tenantId);

            $cloned = DynamicPrintTemplate::create([
                'tenant_id' => $tenantId,
                'entity_definition_id' => $targetEntityDefinitionId,
                'source_print_template_id' => $source->getKey(),
                'owner_scope' => 'tenant',
                'template_key' => $source->template_key,
                'action_key' => $source->action_key,
                'name' => $source->name,
                'description' => $source->description,
                'blocks_json' => $source->blocks_json,
                'placeholder_whitelist' => $source->placeholder_whitelist,
                'status' => 'draft',
                'is_locked' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            event(new TenantBlueprintCloned(
                blueprintType: 'print_template',
                sourceId: (string) $source->getKey(),
                targetId: (string) $cloned->getKey(),
                tenantId: $tenantId,
                actorId: $userId,
            ));

            return $cloned;
        });
    }

    /**
     * @throws ValidationException
     */
    private function resolveTenantEntityDefinitionId(DynamicPrintTemplate $source, string $tenantId): ?string
    {
        if ($source->entity_definition_id === null) {
            return null;
        }

        $sourceEntityId = (string) $source->entity_definition_id;

        $tenantEntity = EntityDefinition::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($sourceEntityId): void {
                $query->where('id', $sourceEntityId)
                    ->orWhere('source_entity_definition_id', $sourceEntityId);
            })
            ->first();

        if (! $tenantEntity instanceof EntityDefinition) {
            throw ValidationException::withMessages([
                'entity_definition_id' => 'Clona primero la entidad del blueprint para vincular esta plantilla en el tenant.',
            ]);
        }

        return (string) $tenantEntity->getKey();
    }
}
