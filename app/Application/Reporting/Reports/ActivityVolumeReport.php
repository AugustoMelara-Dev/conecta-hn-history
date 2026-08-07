<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

class ActivityVolumeReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::ActivityVolume; }
    public function getName(): string { return 'Volumen de actividades'; }
    public function getDescription(): string { return 'Actividades de CRM por día.'; }
    public function getChartType(): string { return 'line'; }
    public function getColumns(): array { return ['Fecha', 'Actividades']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $rows = DB::table('crm_activities')->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as cnt')->groupBy('date')->orderBy('date')->get();
        $labels = []; $values = []; $data = [];
        foreach ($rows as $r) { $labels[] = $r->date; $values[] = (int) $r->cnt; $data[] = new ReportRow($r->date, (int) $r->cnt, unit: '#'); }
        return new ReportData(rows: $data, series: [new ReportSeries('Actividades', $labels, $values, 'line', '#10B981')], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
