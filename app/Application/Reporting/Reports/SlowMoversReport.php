<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

class SlowMoversReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::SlowMovers;
    }

    public function getName(): string
    {
        return 'Productos lentos';
    }

    public function getDescription(): string
    {
        return 'Productos con baja rotación.';
    }

    public function getChartType(): string
    {
        return 'table';
    }

    public function getColumns(): array
    {
        return ['Producto', 'Stock', 'Última venta'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [], series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
