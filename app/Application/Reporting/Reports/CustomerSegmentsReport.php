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

class CustomerSegmentsReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::CustomerSegments;
    }

    public function getName(): string
    {
        return 'Segmentos de clientes';
    }

    public function getDescription(): string
    {
        return 'Segmentación RFM.';
    }

    public function getChartType(): string
    {
        return 'pie';
    }

    public function getColumns(): array
    {
        return ['Segmento', 'Clientes', '%'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $segments = DB::table('customer_profiles')
            ->where('tenant_id', $tenantId)
            ->selectRaw("COALESCE(NULLIF(manual_segment, ''), NULLIF(customer_segment, ''), 'Sin segmento') as segment, COUNT(*) as customers")
            ->groupBy('segment')
            ->orderByDesc('customers')
            ->orderBy('segment')
            ->get();

        $total = (int) $segments->sum('customers');
        $rows = $segments->map(static fn (object $segment): ReportRow => new ReportRow(
            (string) $segment->segment,
            (int) $segment->customers,
            unit: 'clientes',
            metadata: ['percentage' => $total > 0 ? round(((int) $segment->customers / $total) * 100, 2) : 0.0],
        ))->all();

        return new ReportData(
            rows: $rows,
            series: [new ReportSeries(
                'Clientes',
                $segments->pluck('segment')->map(static fn ($value): string => (string) $value)->all(),
                $segments->pluck('customers')->map(static fn ($value): int => (int) $value)->all(),
                'pie',
            )],
            summary: ['total_customers' => $total],
            metadata: ReportAvailability::metadata($range, ['source' => 'customer_profiles']),
        );
    }
}
