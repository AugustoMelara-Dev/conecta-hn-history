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

class InventoryTurnoverReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::InventoryTurnover;
    }

    public function getName(): string
    {
        return 'Rotación de inventario';
    }

    public function getDescription(): string
    {
        return 'Veces que rota el inventario por período.';
    }

    public function getChartType(): string
    {
        return 'gauge';
    }

    public function getColumns(): array
    {
        return ['Categoría', 'Rotación'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $products = DB::table('products as p')
            ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
            ->leftJoin('orders as o', function ($join) use ($tenantId, $range): void {
                $join->on('o.id', '=', 'oi.order_id')
                    ->where('o.tenant_id', $tenantId)
                    ->where('o.payment_status', 'paid')
                    ->whereBetween('o.created_at', [$range->start, $range->end]);
            })
            ->where('p.tenant_id', $tenantId)
            ->where('p.manage_stock', true)
            ->selectRaw('p.id, p.name, p.stock_quantity, COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) as units_sold')
            ->groupBy('p.id', 'p.name', 'p.stock_quantity')
            ->orderByDesc('units_sold')
            ->limit(25)
            ->get();

        $rows = [];
        $labels = [];
        $values = [];
        foreach ($products as $product) {
            $sold = (int) $product->units_sold;
            $stock = (int) $product->stock_quantity;
            $availableUnits = $sold + $stock;
            $turnover = $availableUnits > 0 ? $sold / $availableUnits : 0.0;
            $rows[] = new ReportRow(
                (string) $product->name,
                round($turnover, 4),
                unit: 'ratio',
                metadata: ['units_sold' => $sold, 'stock_quantity' => $stock],
            );
            $labels[] = (string) $product->name;
            $values[] = round($turnover * 100, 2);
        }

        return new ReportData(
            rows: $rows,
            series: [new ReportSeries('Rotación operacional %', $labels, $values, 'bar')],
            summary: ['products' => count($rows), 'units_sold' => (int) $products->sum('units_sold')],
            metadata: ReportAvailability::metadata($range, [
                'source' => 'order_items + products.stock_quantity',
                'formula' => 'units_sold / (units_sold + current_stock_quantity)',
                'qualification' => 'operational sell-through proxy; not accounting inventory turnover',
            ]),
        );
    }
}
