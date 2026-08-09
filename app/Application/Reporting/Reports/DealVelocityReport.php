<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DealVelocityReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::DealVelocity; }
    public function getName(): string { return 'Velocidad de deals'; }
    public function getDescription(): string { return 'Tiempo promedio de lead a won.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Pipeline', 'Días promedio', 'Deals cerrados']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $deals = DB::table('crm_deals as d')
            ->join('crm_pipelines as p', function ($join) use ($tenantId): void {
                $join->on('p.id', '=', 'd.crm_pipeline_id')
                    ->where('p.tenant_id', $tenantId);
            })
            ->where('d.tenant_id', $tenantId)
            ->where('d.status', 'won')
            ->whereNotNull('d.closed_at')
            ->whereBetween('d.closed_at', [$range->start, $range->end])
            ->select(['d.crm_pipeline_id', 'p.name', 'd.created_at', 'd.closed_at'])
            ->orderBy('d.crm_pipeline_id')
            ->get();

        $grouped = [];
        foreach ($deals as $deal) {
            $pipelineId = (int) $deal->crm_pipeline_id;
            $grouped[$pipelineId] ??= [
                'name' => (string) $deal->name,
                'days' => [],
            ];

            $createdAt = CarbonImmutable::parse($deal->created_at);
            $closedAt = CarbonImmutable::parse($deal->closed_at);
            $grouped[$pipelineId]['days'][] = $createdAt->diffInSeconds($closedAt) / 86400;
        }

        $rows = [];
        foreach ($grouped as $stats) {
            $days = $stats['days'];
            $rows[] = new ReportRow(
                $stats['name'],
                round(array_sum($days) / count($days), 1),
                unit: 'días',
                metadata: ['count' => count($days)],
            );
        }

        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
