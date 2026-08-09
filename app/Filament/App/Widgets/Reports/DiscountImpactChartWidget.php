<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class DiscountImpactChartWidget extends ChartWidget
{
    protected ?string $heading = 'Impacto de descuentos';

    protected static ?int $sort = 24;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::DiscountEffectiveness, 'Ingresos con cupón');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
