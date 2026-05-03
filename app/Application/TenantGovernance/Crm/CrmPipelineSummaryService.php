<?php

declare(strict_types=1);

namespace App\Application\TenantGovernance\Crm;

use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class CrmPipelineSummaryService
{
    /**
     * @return array{pipeline: array<string, mixed>|null, totals: array<string, mixed>, stages: list<array<string, mixed>>}
     */
    public function summarize(Tenant $tenant, ?CrmPipeline $pipeline = null): array
    {
        $pipeline ??= CrmPipeline::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if (! $pipeline instanceof CrmPipeline) {
            return [
                'pipeline' => null,
                'totals' => [
                    'open_count' => 0,
                    'open_value' => '0.00',
                    'won_value' => '0.00',
                    'lost_value' => '0.00',
                    'overdue_count' => 0,
                ],
                'stages' => [],
            ];
        }

        $deals = CrmDeal::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('crm_pipeline_id', $pipeline->getKey())
            ->get();

        return [
            'pipeline' => [
                'id' => $pipeline->getKey(),
                'name' => $pipeline->name,
                'key' => $pipeline->key,
            ],
            'totals' => $this->totals($deals),
            'stages' => $this->stages($tenant, $pipeline, $deals),
        ];
    }

    /**
     * @param  Collection<int, CrmDeal>  $deals
     * @return array<string, mixed>
     */
    private function totals(Collection $deals): array
    {
        return [
            'open_count' => $deals->where('status', CrmDeal::STATUS_OPEN)->count(),
            'open_value' => number_format((float) $deals->where('status', CrmDeal::STATUS_OPEN)->sum('value'), 2, '.', ''),
            'won_value' => number_format((float) $deals->where('status', CrmDeal::STATUS_WON)->sum('value'), 2, '.', ''),
            'lost_value' => number_format((float) $deals->where('status', CrmDeal::STATUS_LOST)->sum('value'), 2, '.', ''),
            'overdue_count' => $deals
                ->where('status', CrmDeal::STATUS_OPEN)
                ->filter(fn (CrmDeal $deal): bool => $deal->expected_close_at !== null && $deal->expected_close_at->isPast())
                ->count(),
        ];
    }

    /**
     * @param  Collection<int, CrmDeal>  $deals
     * @return list<array<string, mixed>>
     */
    private function stages(Tenant $tenant, CrmPipeline $pipeline, Collection $deals): array
    {
        return CrmStage::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('crm_pipeline_id', $pipeline->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (CrmStage $stage) use ($deals): array {
                $stageDeals = $deals->where('crm_stage_id', $stage->getKey());

                return [
                    'id' => $stage->getKey(),
                    'name' => $stage->name,
                    'key' => $stage->key,
                    'type' => $stage->type,
                    'probability' => $stage->probability,
                    'deal_count' => $stageDeals->count(),
                    'deal_value' => number_format((float) $stageDeals->sum('value'), 2, '.', ''),
                ];
            })
            ->values()
            ->all();
    }
}
