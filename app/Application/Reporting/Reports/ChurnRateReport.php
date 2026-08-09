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
    public function getKey(): ReportKey
    {
        return ReportKey::ChurnRate;
    }

    public function getName(): string
    {
        return 'Tasa de churn';
    }

    public function getDescription(): string
    {
        return 'Porcentaje de clientes de la cohorte inicial que no regresaron.';
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
        $cohort = DB::table('orders as cohort')
            ->where('cohort.tenant_id', $tenantId)
            ->where('cohort.payment_status', 'paid')
            ->whereNotNull('cohort.customer_email')
            ->where('cohort.customer_email', '<>', '')
            ->where('cohort.created_at', '<', $range->start);

        $startCustomers = (clone $cohort)
            ->distinct()
            ->count('cohort.customer_email');

        $retainedCustomers = (clone $cohort)
            ->whereExists(function ($query) use ($tenantId, $range): void {
                $query->selectRaw('1')
                    ->from('orders as repeated')
                    ->whereColumn('repeated.customer_email', 'cohort.customer_email')
                    ->where('repeated.tenant_id', $tenantId)
                    ->where('repeated.payment_status', 'paid')
                    ->whereBetween('repeated.created_at', [$range->start, $range->end]);
            })
            ->distinct()
            ->count('cohort.customer_email');

        $lostCustomers = $startCustomers - $retainedCustomers;
        $churnRate = $startCustomers > 0 ? ($lostCustomers / $startCustomers) * 100 : 0;

        return new ReportData(
            rows: [
                new ReportRow('Churn rate', round($churnRate, 2), unit: '%'),
                new ReportRow('Clientes al inicio', $startCustomers),
                new ReportRow('Clientes retenidos', $retainedCustomers),
                new ReportRow('Clientes perdidos', $lostCustomers),
            ],
            series: [],
            summary: ['start' => $startCustomers, 'retained' => $retainedCustomers, 'lost' => $lostCustomers],
            metadata: ['range' => $range->toArray()],
        );
    }
}
