<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use Filament\Widgets\ChartWidget;

class LeadScoreDistribution extends ChartWidget
{
    protected ?string $heading = 'Lead Score Distribution';
    protected static ?int $sort = 16;

    protected function getData(): array { return ['datasets' => [], 'labels' => ['Hot', 'Warm', 'Qualified', 'Cold']]; }
    protected function getType(): string { return 'pie'; }
}
