<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;

class RefundRateReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::RefundRate; }
    public function getName(): string { return 'Tasa de reembolso'; }
    public function getDescription(): string { return 'Porcentaje de órdenes reembolsadas.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Métrica', 'Valor']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $total = DB::table('orders')->where('tenant_id', $tenantId)->whereBetween('created_at', [$range->start, $range->end])->count();
        $refunded = DB::table('orders')->where('tenant_id', $tenantId)->where('status', 'refunded')->whereBetween('created_at', [$range->start, $range->end])->count();
        $rate = $total > 0 ? ($refunded / $total) * 100 : 0;
        return new ReportData(rows: [new ReportRow('Tasa de reembolso', round($rate, 2), unit: '%')], series: [], summary: ['rate' => round($rate, 2)], metadata: ['range' => $range->toArray()]);
    }
}
