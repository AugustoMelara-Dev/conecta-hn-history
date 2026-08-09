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
 * TopProductsReport - Top N productos más vendidos.
 */
class TopProductsReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::TopProducts;
    }

    public function getName(): string
    {
        return 'Top productos';
    }

    public function getDescription(): string
    {
        return 'Productos más vendidos por ingresos.';
    }

    public function getChartType(): string
    {
        return 'table';
    }

    public function getColumns(): array
    {
        return ['Producto', 'Unidades vendidas', 'Ingresos (HNL)'];
    }

    public function getFilters(): array
    {
        return ['limit' => 'int', 'sort_by' => 'string'];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $topProducts = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.status', 'paid')
            ->whereBetween('o.created_at', [$range->start, $range->end])
            ->selectRaw('p.id, p.name, SUM(oi.quantity) as units, SUM(oi.subtotal) as revenue')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        $rows = [];
        $labels = [];
        $values = [];

        foreach ($topProducts as $product) {
            $rows[] = new ReportRow($product->name, (float) $product->revenue, unit: 'HNL', metadata: ['units' => (int) $product->units]);
            $labels[] = $product->name;
            $values[] = (float) $product->revenue;
        }

        return new ReportData(
            rows: $rows,
            series: [new ReportSeries('Revenue', $labels, $values, 'bar', '#4F46E5')],
            summary: ['top_count' => count($rows), 'total_revenue' => array_sum($values)],
            metadata: ['range' => $range->toArray()],
        );
    }
}
