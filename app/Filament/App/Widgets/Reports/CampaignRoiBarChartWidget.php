<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class CampaignRoiBarChartWidget extends ChartWidget
{
    protected ?string $heading = 'ROI de campañas';

    protected static ?int $sort = 7;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::CampaignRoi, 'ROI');
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
