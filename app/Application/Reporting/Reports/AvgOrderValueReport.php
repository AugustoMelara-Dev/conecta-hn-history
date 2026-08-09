<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;
use Illuminate\Support\Facades\DB;

class AvgOrderValueReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::AvgOrderValue;
    }

    public function getName(): string
    {
        return 'Ticket promedio';
    }

    public function getDescription(): string
    {
        return 'Average Order Value (AOV).';
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
        $aov = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$range->start, $range->end])
            ->avg('total_amount') ?? 0;

        $previousAov = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('created_at', [$range->comparisonStart ?? now()->subDays(60), $range->comparisonEnd ?? now()->subDays(30)])
            ->avg('total_amount') ?? 0;

        return new ReportData(
            rows: [new ReportRow('AOV', round((float) $aov, 2), previousValue: round((float) $previousAov, 2), unit: 'HNL')],
            series: [],
            summary: ['current_aov' => (float) $aov, 'previous_aov' => (float) $previousAov],
            metadata: ['range' => $range->toArray()],
        );
    }
}
