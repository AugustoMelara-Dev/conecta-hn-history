<?php

declare(strict_types=1);

namespace App\Application\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    public function averageDaysPerStage(string $tenantId, int|string|null $pipelineId = null): array
    {
        if (! Schema::hasTable('crm_deal_stage_history')) {
            return [];
        }

        $query = DB::table('crm_deal_stage_history as h')
            ->join('crm_deals as d', 'd.id', '=', 'h.deal_id')
            ->where('d.tenant_id', $tenantId)
            ->whereNotNull('h.entered_at')
            ->whereNotNull('h.exited_at');

        if ($pipelineId) {
            $query->where('d.crm_pipeline_id', $pipelineId);
        }

        $elapsedDays = $this->elapsedDaysExpression('h.exited_at', 'h.entered_at');
        $rows = $query
            ->select('h.crm_stage_id', DB::raw("AVG({$elapsedDays}) as avg_days"))
            ->groupBy('h.crm_stage_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->crm_stage_id] = (float) $row->avg_days;
        }

        return $result;
    }

    public function averageDaysToClose(string $tenantId, string $status = 'won'): float
    {
        return (float) DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('status', $status)
            ->whereNotNull('closed_at')
            ->selectRaw('AVG('.$this->elapsedDaysExpression('closed_at', 'created_at').') as avg_days')
            ->value('avg_days') ?? 0;
    }

    /**
     * @return array<int, array{stage_id: string, stage_name: string, avg_days: float, deal_count: int}>
     */
    public function bottlenecks(string $tenantId, int|string|null $pipelineId = null, int $limit = 5): array
    {
        if (! Schema::hasTable('crm_deal_stage_history')) {
            return [];
        }

        return DB::table('crm_deal_stage_history as h')
            ->join('crm_deals as d', 'd.id', '=', 'h.deal_id')
            ->join('crm_stages as s', 's.id', '=', 'h.crm_stage_id')
            ->where('d.tenant_id', $tenantId)
            ->whereNotNull('h.entered_at')
            ->whereNotNull('h.exited_at')
            ->when($pipelineId, fn ($q) => $q->where('d.crm_pipeline_id', $pipelineId))
            ->groupBy('h.crm_stage_id', 's.name')
            ->selectRaw('h.crm_stage_id, s.name as stage_name, AVG('.$this->elapsedDaysExpression('h.exited_at', 'h.entered_at').') as avg_days, COUNT(*) as deal_count')
            ->orderByDesc('avg_days')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'stage_id' => $r->crm_stage_id,
                'stage_name' => $r->stage_name,
                'avg_days' => (float) $r->avg_days,
                'deal_count' => (int) $r->deal_count,
            ])
            ->all();
    }

    private function elapsedDaysExpression(string $endColumn, string $startColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "julianday({$endColumn}) - julianday({$startColumn})",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$startColumn}, {$endColumn}) / 86400",
            'pgsql' => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn})) / 86400",
            default => throw new \RuntimeException('CRM velocity SQL dialect is unavailable.'),
        };
    }
}
