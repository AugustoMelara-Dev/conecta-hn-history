<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Models\ReportSchedule;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * ReportsSchedule - List/create/edit de programaciones de reportes.
 */
class ReportsSchedule extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $title = 'Programación de reportes';
    protected static string | \UnitEnum | null $navigationGroup = 'Operaciones';
    protected static ?string $navigationLabel = 'Programación';
    protected static ?int $navigationSort = 52;
    protected string $view = 'filament.app.pages.reports.schedule';

    public function getViewData(): array
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        $schedules = $tenantId ? ReportSchedule::where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->get() : collect();
        return ['schedules' => $schedules];
    }

    public function createSchedule(array $data): void
    {
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) {
            Notification::make()->title('No tenant context')->danger()->send();
            return;
        }
        ReportSchedule::create([
            'tenant_id' => $tenantId,
            'report_key' => $data['report_key'],
            'frequency' => $data['frequency'],
            'recipients' => $data['recipients'],
            'format' => $data['format'],
            'is_active' => true,
        ]);
        Notification::make()->title('Programación creada')->success()->send();
    }
}
