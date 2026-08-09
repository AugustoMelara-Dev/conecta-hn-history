<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Widgets\ChartWidget;

class LeadScoreDistribution extends ChartWidget
{
    protected ?string $heading = 'Lead Score Distribution';

    protected static ?int $sort = 16;

    protected function getData(): array
    {
        return ReportWidgetData::chart(ReportKey::LeadScore, 'Contactos');
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
