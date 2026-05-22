<?php

declare(strict_types=1);

namespace App\Infrastructure\Crm;

use App\Application\TenantGovernance\Crm\CrmDefaultPipelineGatewayInterface;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class EloquentCrmDefaultPipelineGateway implements CrmDefaultPipelineGatewayInterface
{
    public function ensureForTenant(object $tenant): object
    {
        if (! $tenant instanceof Tenant) {
            throw new \InvalidArgumentException('CRM default pipeline requires an Eloquent tenant adapter object.');
        }

        $pipeline = CrmPipeline::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_default', true)
            ->first();

        if ($pipeline instanceof CrmPipeline) {
            $this->ensureDefaultStages($tenant, $pipeline);

            return $pipeline;
        }

        $pipeline = CrmPipeline::query()
            // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
            ->withoutGlobalScopes()
            ->forceCreate([
                'tenant_id' => (string) $tenant->getKey(),
                'name' => 'Pipeline comercial',
                'key' => 'default',
                'description' => 'Pipeline base para oportunidades comerciales reales.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
            ]);

        $this->ensureDefaultStages($tenant, $pipeline);

        return $pipeline;
    }

    private function ensureDefaultStages(Tenant $tenant, CrmPipeline $pipeline): void
    {
        $stages = [
            ['name' => 'Nuevo', 'key' => 'new', 'type' => CrmStage::TYPE_OPEN, 'probability' => 10, 'is_default' => true, 'sort_order' => 10],
            ['name' => 'Calificado', 'key' => 'qualified', 'type' => CrmStage::TYPE_OPEN, 'probability' => 30, 'is_default' => false, 'sort_order' => 20],
            ['name' => 'Propuesta', 'key' => 'proposal', 'type' => CrmStage::TYPE_OPEN, 'probability' => 60, 'is_default' => false, 'sort_order' => 30],
            ['name' => 'Ganado', 'key' => 'won', 'type' => CrmStage::TYPE_WON, 'probability' => 100, 'is_default' => false, 'sort_order' => 40],
            ['name' => 'Perdido', 'key' => 'lost', 'type' => CrmStage::TYPE_LOST, 'probability' => 0, 'is_default' => false, 'sort_order' => 50],
        ];

        foreach ($stages as $stage) {
            $existingStage = CrmStage::query()
                // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
                ->withoutGlobalScopes()
                ->where('tenant_id', (string) $tenant->getKey())
                ->where('crm_pipeline_id', $pipeline->getKey())
                ->where('key', Str::slug((string) $stage['key']))
                ->first();

            if ($existingStage instanceof CrmStage) {
                continue;
            }

            CrmStage::query()
                // ADMIN_CROSS_TENANT: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
                ->withoutGlobalScopes()
                ->forceCreate($stage + [
                    'tenant_id' => (string) $tenant->getKey(),
                    'crm_pipeline_id' => $pipeline->getKey(),
                ]);
        }
    }
}
