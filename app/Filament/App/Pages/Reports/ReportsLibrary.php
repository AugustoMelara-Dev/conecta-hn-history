<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use Filament\Pages\Page;

class ReportsLibrary extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $title = 'Biblioteca de reportes';
    protected static string | \UnitEnum | null $navigationGroup = 'Operaciones';
    protected static ?string $navigationLabel = 'Biblioteca';
    protected static ?int $navigationSort = 53;
    protected string $view = 'filament.app.pages.reports.library';
}
