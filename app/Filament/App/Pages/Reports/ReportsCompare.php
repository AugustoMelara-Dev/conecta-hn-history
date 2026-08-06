<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use Filament\Pages\Page;

class ReportsCompare extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $title = 'Comparar reportes';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $navigationLabel = 'Comparar';
    protected static ?int $navigationSort = 54;
    protected static string $view = 'filament.app.pages.reports.compare';
}
