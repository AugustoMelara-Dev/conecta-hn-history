<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Models\CrmDeal;
use Illuminate\Support\Facades\DB;

/**
 * DealVelocityService - Mide velocidad del deal en días promedio por stage.
 *
 * Calcula:
 * - Promedio de días en cada stage antes de moverse
 * - Tiempo promedio total de lead a won
 * - Identifica cuellos de botella (stages donde los deals se quedan más tiempo)
 */
class DealVelocityService
{
    /**
     * @return array<string, float> promedio de días por stage_id
     */
    public function averageDaysPerStage(string $tenantId, ?string $pipelineId = null): array
    {
        $query = DB::table('crm_deal_stage_history as h')
            ->join('crm_deals as d', 'd.id', '=', 'h.deal_id')
            ->where('d.tenant_id', $tenantId)
            ->whereNotNull('h.entered_at')
            ->whereNotNull('h.exited_at');

        if ($pipelineId) {
            $query->where('d.pipeline_id', $pipelineId);
        }

        $rows = $query
            ->select('h.stage_id', DB::raw('AVG(EXTRACT(EPOCH FROM (h.exited_at - h.entered_at))/86400) as avg_days'))
            ->groupBy('h.stage_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->stage_id] = (float) $row->avg_days;
        }
        return $result;
    }

    public function averageDaysToClose(string $tenantId, string $status = 'won'): float
    {
        return (float) DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('status', $status)
            ->whereNotNull('won_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (won_at - created_at))/86400) as avg_days')
            ->value('avg_days') ?? 0;
    }

    /**
     * @return array<int, array{stage_id: string, stage_name: string, avg_days: float, deal_count: int}>
     */
    public function bottlenecks(string $tenantId, ?string $pipelineId = null, int $limit = 5): array
    {
        return DB::table('crm_deal_stage_history as h')
            ->join('crm_deals as d', 'd.id', '=', 'h.deal_id')
            ->join('crm_stages as s', 's.id', '=', 'h.stage_id')
            ->where('d.tenant_id', $tenantId)
            ->whereNotNull('h.entered_at')
            ->whereNotNull('h.exited_at')
            ->when($pipelineId, fn ($q) => $q->where('d.pipeline_id', $pipelineId))
            ->groupBy('h.stage_id', 's.name')
            ->selectRaw('h.stage_id, s.name as stage_name, AVG(EXTRACT(EPOCH FROM (h.exited_at - h.entered_at))/86400) as avg_days, COUNT(*) as deal_count')
            ->orderByDesc('avg_days')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'stage_id' => $r->stage_id,
                'stage_name' => $r->stage_name,
                'avg_days' => (float) $r->avg_days,
                'deal_count' => (int) $r->deal_count,
            ])
            ->all();
    }
}
