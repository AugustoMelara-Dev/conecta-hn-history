<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
class GeographicDistributionReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::GeographicDistribution; }
    public function getName(): string { return 'Distribución geográfica'; }
    public function getDescription(): string { return 'Clientes por país/ciudad.'; }
    public function getChartType(): string { return 'map'; }
    public function getColumns(): array { return ['País', 'Clientes', 'Ingresos']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
