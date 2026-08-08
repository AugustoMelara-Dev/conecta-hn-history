<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
class AttributionBySourceReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::AttributionBySource; }
    public function getName(): string { return 'Atribución por fuente'; }
    public function getDescription(): string { return 'Ingresos por source attribution.'; }
    public function getChartType(): string { return 'pie'; }
    public function getColumns(): array { return ['Fuente', 'Ingresos (HNL)', '%']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $sources = DB::table('orders as o')->join('crm_contacts as c', 'c.email', '=', 'o.customer_email')
            ->where('o.tenant_id', $tenantId)->where('o.status', 'paid')
            ->whereBetween('o.created_at', [$range->start, $range->end])
            ->selectRaw('COALESCE(c.source, "unknown") as source, SUM(o.total_amount) as revenue')
            ->groupBy('source')->get();
        $total = (float) $sources->sum('revenue');
        $rows = [];
        foreach ($sources as $s) {
            $pct = $total > 0 ? ((float) $s->revenue / $total) * 100 : 0;
            $rows[] = new ReportRow($s->source, (float) $s->revenue, unit: 'HNL', metadata: ['pct' => round($pct, 1)]);
        }
        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
