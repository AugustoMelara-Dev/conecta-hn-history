<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use Illuminate\Support\Facades\DB;

class CustomerLtvReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::CustomerLtv;
    }

    public function getName(): string
    {
        return 'LTV del cliente';
    }

    public function getDescription(): string
    {
        return 'Customer Lifetime Value promedio.';
    }

    public function getChartType(): string
    {
        return 'gauge';
    }

    public function getColumns(): array
    {
        return ['Segmento', 'LTV (HNL)', 'Órdenes promedio'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $ltvData = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->selectRaw('customer_email, COUNT(*) as order_count, SUM(total_amount) as total_spent')
            ->groupBy('customer_email')
            ->get();

        $segmentLtv = [];
        foreach ($ltvData as $row) {
            $segment = match (true) {
                $row->order_count >= 10 => 'VIP',
                $row->order_count >= 5 => 'Frecuente',
                $row->order_count >= 2 => 'Recurrente',
                default => 'Nuevo',
            };
            $segmentLtv[$segment] ??= ['total' => 0, 'count' => 0];
            $segmentLtv[$segment]['total'] += (float) $row->total_spent;
            $segmentLtv[$segment]['count']++;
        }

        $rows = [];
        foreach ($segmentLtv as $segment => $data) {
            $ltv = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
            $avgOrders = $data['count'] > 0 ? $ltvData->where('customer_email', '!=', '')->count() / $data['count'] : 0;
            $rows[] = new ReportRow($segment, round($ltv, 2), unit: 'HNL', metadata: ['avg_orders' => round($avgOrders, 1)]);
        }

        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
