<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
class CohortRetentionReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::CohortRetention; }
    public function getName(): string { return 'Retención por cohorte'; }
    public function getDescription(): string { return 'Retención de cohortes mensuales.'; }
    public function getChartType(): string { return 'heatmap'; }
    public function getColumns(): array { return ['Cohorte', 'Mes 0', 'Mes 1', 'Mes 2', 'Mes 3']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: ['placeholder' => true], metadata: ['range' => $range->toArray()]);
    }
}
