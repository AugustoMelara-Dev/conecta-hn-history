<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Spatie\LaravelSettings\Facades\Settings;

/**
 * GlobalSettings - Global settings page.
 */
class GlobalSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static string | \UnitEnum | null $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Global Settings';
    protected static ?string $title = 'Global Settings';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.admin.pages.global-settings';
}
