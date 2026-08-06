<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use Filament\Pages\Page;

/**
 * ReportsOverview - Grid de 25 cards; click para ver detalle.
 */
class ReportsOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $title = 'Reportes';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $navigationLabel = 'Reportes';
    protected static ?int $navigationSort = 50;
    protected static string $view = 'filament.app.pages.reports.overview';

    public function getViewData(): array
    {
        $registry = app(\App\Application\Reporting\ReportRegistry::class);
        return ['reportsByCategory' => $registry->grouped()];
    }
}
