<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use Illuminate\Support\Facades\DB;

class ChurnRateReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::ChurnRate; }
    public function getName(): string { return 'Tasa de churn'; }
    public function getDescription(): string { return 'Porcentaje de clientes que cancelaron.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Métrica', 'Valor']; }
    public function getFilters(): array { return []; }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $startCustomers = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '<', $range->start)
            ->distinct()
            ->count('customer_email');

        $churnedCustomers = DB::table('subscription_payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'cancelled')
            ->whereBetween('updated_at', [$range->start, $range->end])
            ->distinct()
            ->count('customer_email');

        $churnRate = $startCustomers > 0 ? ($churnedCustomers / $startCustomers) * 100 : 0;

        return new ReportData(
            rows: [
                new ReportRow('Churn rate', round($churnRate, 2), unit: '%'),
                new ReportRow('Clientes al inicio', $startCustomers),
                new ReportRow('Clientes perdidos', $churnedCustomers),
            ],
            series: [],
            summary: ['churn_rate' => round($churnRate, 2)],
            metadata: ['range' => $range->toArray()],
        );
    }
}
