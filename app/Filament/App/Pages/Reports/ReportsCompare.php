<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use Filament\Pages\Page;

class ReportsCompare extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $title = 'Comparar reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Comparar';

    protected static ?int $navigationSort = 54;

    protected string $view = 'filament.app.pages.reports.compare';
}
