<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportSeries;
use Illuminate\Support\Facades\DB;

/**
 * RevenueByMonthReport - Ingresos agregados por mes.
 *
 * Datos: sum de total_amount por mes, agrupados por created_at.
 */
class RevenueByMonthReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::RevenueByMonth;
    }

    public function getName(): string
    {
        return 'Ingresos por mes';
    }

    public function getDescription(): string
    {
        return 'Evolución mensual de ingresos por órdenes pagadas.';
    }

    public function getChartType(): string
    {
        return 'line';
    }

    public function getColumns(): array
    {
        return ['Mes', 'Ingresos (HNL)', 'Órdenes', 'Cambio %'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $currentRows = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, SUM(total_amount) as revenue, COUNT(*) as order_count")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $rows = [];
        $labels = [];
        $values = [];

        foreach ($currentRows as $row) {
            $rows[] = new ReportRow(
                label: $row->month,
                value: (float) $row->revenue,
                unit: 'HNL',
                metadata: ['order_count' => (int) $row->order_count],
            );
            $labels[] = $row->month;
            $values[] = (float) $row->revenue;
        }

        $series = [
            new ReportSeries(
                name: 'Ingresos',
                labels: $labels,
                values: $values,
                type: 'line',
                color: '#4F46E5',
            ),
        ];

        $summary = [
            'total_revenue' => array_sum($values),
            'total_orders' => array_sum(array_column($rows, 'metadata')),
            'avg_per_month' => count($values) > 0 ? array_sum($values) / count($values) : 0,
        ];

        return new ReportData(
            rows: $rows,
            series: $series,
            summary: $summary,
            metadata: ['range' => $range->toArray()],
        );
    }
}
