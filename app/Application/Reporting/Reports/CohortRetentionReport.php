<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CohortRetentionReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::CohortRetention;
    }

    public function getName(): string
    {
        return 'Retención por cohorte';
    }

    public function getDescription(): string
    {
        return 'Retención de cohortes mensuales.';
    }

    public function getChartType(): string
    {
        return 'heatmap';
    }

    public function getColumns(): array
    {
        return ['Cohorte', 'Mes 0', 'Mes 1', 'Mes 2', 'Mes 3'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $orders = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereNotNull('customer_email')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->orderBy('created_at')
            ->get(['customer_email', 'created_at']);

        $customers = [];
        foreach ($orders as $order) {
            $email = mb_strtolower(trim((string) $order->customer_email));
            if ($email === '') {
                continue;
            }

            $month = CarbonImmutable::parse($order->created_at)->startOfMonth();
            $customers[$email] ??= ['cohort' => $month, 'months' => []];
            $offset = $customers[$email]['cohort']->diffInMonths($month);
            if ($offset >= 0 && $offset <= 3) {
                $customers[$email]['months'][(int) $offset] = true;
            }
        }

        $cohorts = [];
        foreach ($customers as $customer) {
            $key = $customer['cohort']->format('Y-m');
            $cohorts[$key] ??= [0 => 0, 1 => 0, 2 => 0, 3 => 0];
            foreach (array_keys($customer['months']) as $offset) {
                $cohorts[$key][$offset]++;
            }
        }
        ksort($cohorts);

        $rows = [];
        foreach ($cohorts as $cohort => $counts) {
            $size = max(1, $counts[0]);
            $rows[] = new ReportRow(
                $cohort,
                $counts[0],
                unit: 'clientes',
                metadata: [
                    'month_0_pct' => 100.0,
                    'month_1_pct' => round(($counts[1] / $size) * 100, 2),
                    'month_2_pct' => round(($counts[2] / $size) * 100, 2),
                    'month_3_pct' => round(($counts[3] / $size) * 100, 2),
                ],
            );
        }

        $labels = array_keys($cohorts);
        $series = [];
        foreach ([0, 1, 2, 3] as $offset) {
            $series[] = new ReportSeries(
                "Mes {$offset}",
                $labels,
                array_map(static function (array $counts) use ($offset): float {
                    $size = max(1, $counts[0]);

                    return round(($counts[$offset] / $size) * 100, 2);
                }, array_values($cohorts)),
                'bar',
            );
        }

        return new ReportData(
            rows: $rows,
            series: $series,
            summary: ['cohorts' => count($cohorts), 'customers' => count($customers)],
            metadata: ReportAvailability::metadata($range, [
                'method' => 'first paid order inside selected range',
            ]),
        );
    }
}
