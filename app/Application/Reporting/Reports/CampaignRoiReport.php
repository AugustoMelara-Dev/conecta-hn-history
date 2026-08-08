<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
class CampaignRoiReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::CampaignRoi; }
    public function getName(): string { return 'ROI de campañas'; }
    public function getDescription(): string { return 'ROI de campañas de marketing.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Campaña', 'ROI %', 'Costo (HNL)', 'Ingresos (HNL)']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
