<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

class ProductMarginReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::ProductMargin;
    }

    public function getName(): string
    {
        return 'Margen por producto';
    }

    public function getDescription(): string
    {
        return 'Margen bruto por producto.';
    }

    public function getChartType(): string
    {
        return 'bar';
    }

    public function getColumns(): array
    {
        return ['Producto', 'Margen %', 'Margen (HNL)'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return ReportAvailability::unavailable(
            $range,
            'El catálogo conserva precio de venta, pero no una base de costo auditable por producto.',
            ['products.cost_amount', 'order_items.unit_cost'],
        );
    }
}
