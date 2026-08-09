<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use Illuminate\Support\Facades\DB;

class SlowMoversReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::SlowMovers;
    }

    public function getName(): string
    {
        return 'Productos lentos';
    }

    public function getDescription(): string
    {
        return 'Productos con baja rotación.';
    }

    public function getChartType(): string
    {
        return 'table';
    }

    public function getColumns(): array
    {
        return ['Producto', 'Stock', 'Última venta'];
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
            ->where('p.stock_quantity', '>', 0)
            ->selectRaw('p.id, p.name, p.stock_quantity, COALESCE(SUM(CASE WHEN o.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) as units_sold, MAX(o.created_at) as last_paid_sale_at')
            ->groupBy('p.id', 'p.name', 'p.stock_quantity')
            ->orderBy('units_sold')
            ->orderByDesc('p.stock_quantity')
            ->limit(20)
            ->get();

        $rows = $products->map(static fn (object $product): ReportRow => new ReportRow(
            (string) $product->name,
            (int) $product->stock_quantity,
            unit: 'unidades en stock',
            metadata: [
                'units_sold' => (int) $product->units_sold,
                'last_paid_sale_at' => $product->last_paid_sale_at,
            ],
        ))->all();

        return new ReportData(
            rows: $rows,
            series: [],
            summary: ['products' => count($rows), 'stock_units' => (int) $products->sum('stock_quantity')],
            metadata: ReportAvailability::metadata($range, ['source' => 'order_items + products.stock_quantity']),
        );
    }
}
