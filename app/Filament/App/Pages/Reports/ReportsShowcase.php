<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * ReportsShowcase - Vista detallada de un Report.
 */
class ReportsShowcase extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $title = 'Detalle de reporte';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $navigationLabel = 'Detalle';
    protected static ?int $navigationSort = 51;
    protected static string $view = 'filament.app.pages.reports.showcase';

    public ?string $reportKey = null;
    public ?string $range = '30d';

    public function mount(?string $reportKey = null): void
    {
        $this->reportKey = $reportKey ?? request()->query('key');
    }

    public function getViewData(): array
    {
        if (! $this->reportKey) {
            return ['report' => null, 'data' => null];
        }
        $key = ReportKey::tryFrom($this->reportKey);
        if ($key === null) {
            return ['report' => null, 'data' => null];
        }
        $registry = app(\App\Application\Reporting\ReportRegistry::class);
        if (! $registry->has($key)) {
            return ['report' => null, 'data' => null];
        }
        $report = $registry->get($key);
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return ['report' => $report, 'data' => null];
        }
        $executor = app(\App\Application\Reporting\ReportExecutor::class);
        $data = $executor->execute($key, $tenantId, ReportRange::fromPreset($this->range ?? '30d'));
        return ['report' => $report, 'data' => $data->toArray()];
    }

    public function export(string $format): mixed
    {
        $key = ReportKey::tryFrom($this->reportKey ?? '');
        $registry = app(\App\Application\Reporting\ReportRegistry::class);
        if ($key === null || ! $registry->has($key)) {
            return null;
        }
        $tenantId = \Filament\Facades\Filament::getTenant()?->getKey();
        if (! $tenantId) return null;
        try {
            $exporter = app(\App\Application\Reporting\ReportExporter::class);
            $report = $registry->get($key);
            $path = $exporter->export($report, $tenantId, ReportRange::fromPreset($this->range ?? '30d'), ReportFormat::tryFrom($format) ?? ReportFormat::Pdf);
            return response()->download($path);
        } catch (\Throwable $e) {
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
            return null;
        }
    }
}
