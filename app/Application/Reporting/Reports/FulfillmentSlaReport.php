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

class FulfillmentSlaReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::FulfillmentSla;
    }

    public function getName(): string
    {
        return 'SLA de fulfillment';
    }

    public function getDescription(): string
    {
        return 'Cumplimiento de SLA de fulfillment.';
    }

    public function getChartType(): string
    {
        return 'gauge';
    }

    public function getColumns(): array
    {
        return ['Métrica', 'Valor'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $targetHours = 24.0;
        $orders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('delivered_at')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->get(['created_at', 'delivered_at']);

        $durations = $orders->map(static fn (object $order): float => max(
            0.0,
            CarbonImmutable::parse($order->created_at)->diffInSeconds(CarbonImmutable::parse($order->delivered_at)) / 3600,
        ));
        $delivered = $durations->count();
        $onTime = $durations->filter(static fn (float $hours): bool => $hours <= $targetHours)->count();
        $rate = $delivered > 0 ? ($onTime / $delivered) * 100 : 0.0;
        $average = $delivered > 0 ? (float) $durations->average() : 0.0;

        return new ReportData(
            rows: [
                new ReportRow('Cumplimiento SLA', round($rate, 2), unit: '%'),
                new ReportRow('Tiempo promedio', round($average, 2), unit: 'horas'),
                new ReportRow('Órdenes entregadas', $delivered),
            ],
            series: [],
            summary: [
                'sla_target_hours' => $targetHours,
                'delivered_orders' => $delivered,
                'on_time_orders' => $onTime,
                'compliance_rate' => round($rate, 2),
                'average_hours' => round($average, 2),
            ],
            metadata: ReportAvailability::metadata($range, ['source' => 'orders.delivered_at']),
        );
    }
}
