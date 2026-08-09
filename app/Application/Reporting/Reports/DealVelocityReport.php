<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;

class DealVelocityReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::DealVelocity;
    }

    public function getName(): string
    {
        return 'Velocidad de deals';
    }

    public function getDescription(): string
    {
        return 'Tiempo promedio de lead a won.';
    }

    public function getChartType(): string
    {
        return 'bar';
    }

    public function getColumns(): array
    {
        return ['Pipeline', 'Días promedio', 'Deals cerrados'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $stats = DB::table('crm_deals')->where('tenant_id', $tenantId)->where('status', 'won')
            ->whereNotNull('won_at')->whereBetween('won_at', [$range->start, $range->end])
            ->selectRaw('pipeline_id, AVG(EXTRACT(EPOCH FROM (won_at - created_at))/86400) as avg_days, COUNT(*) as cnt')
            ->groupBy('pipeline_id')->get();
        $rows = [];
        foreach ($stats as $s) {
            $name = DB::table('crm_pipelines')->where('id', $s->pipeline_id)->value('name') ?? 'Unknown';
            $rows[] = new ReportRow($name, round((float) $s->avg_days, 1), unit: 'días', metadata: ['count' => (int) $s->cnt]);
        }

        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
