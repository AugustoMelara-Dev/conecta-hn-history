<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
class DiscountEffectivenessReport implements ReportInterface
{
    public function getKey(): ReportKey { return ReportKey::DiscountEffectiveness; }
    public function getName(): string { return 'Efectividad de descuentos'; }
    public function getDescription(): string { return 'ROI de cupones aplicados.'; }
    public function getChartType(): string { return 'bar'; }
    public function getColumns(): array { return ['Cupón', 'Usos', 'Descuento total (HNL)', 'Ingresos']; }
    public function getFilters(): array { return []; }
    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
