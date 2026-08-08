<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
class FulfillmentSlaReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::FulfillmentSla; }
    public function getName(): string { return 'SLA de fulfillment'; }
    public function getDescription(): string { return 'Cumplimiento de SLA de fulfillment.'; }
    public function getChartType(): string { return 'gauge'; }
    public function getColumns(): array { return ['Métrica', 'Valor']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
