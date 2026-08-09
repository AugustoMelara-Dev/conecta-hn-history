<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\SqlMonthBucket;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use App\Domain\Reporting\ValueObjects\ReportSeries;
use Illuminate\Support\Facades\DB;

/**
 * MrrArrReport - MRR (Monthly Recurring Revenue) + ARR (Annual Recurring Revenue).
 */
class MrrArrReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::MrrArr;
    }

    public function getName(): string
    {
        return 'MRR / ARR';
    }

    public function getDescription(): string
    {
        return 'Monthly Recurring Revenue y Annual Run Rate.';
    }

    public function getChartType(): string
    {
        return 'line';
    }

    public function getColumns(): array
    {
        return ['Mes', 'MRR (HNL)', 'ARR (HNL)', 'Churn %'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $monthBucket = SqlMonthBucket::expression(DB::connection()->getDriverName());
        $subscriptionRevenue = DB::table('subscription_payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->selectRaw($monthBucket.' as month, SUM(amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $rows = [];
        $labels = [];
        $values = [];

        foreach ($subscriptionRevenue as $row) {
            $mrr = (float) $row->revenue;
            $rows[] = new ReportRow($row->month, $mrr, unit: 'HNL', metadata: ['arr' => $mrr * 12]);
            $labels[] = $row->month;
            $values[] = $mrr;
        }

        return new ReportData(
            rows: $rows,
            series: [new ReportSeries('MRR', $labels, $values, 'line', '#10B981')],
            summary: ['current_mrr' => end($values) ?: 0, 'current_arr' => (end($values) ?: 0) * 12],
            metadata: ['range' => $range->toArray()],
        );
    }
}
