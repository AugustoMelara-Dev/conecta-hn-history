<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget as BaseStatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\TableWidget;
use Filament\Tables;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseReportWidget extends Widget
{
    protected static ?int $sort = 0;

    abstract public function getReportKey(): ReportKey;

    protected function getReportData(): ?ReportData
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) return null;
        $key = $this->getReportKey();
        $registry = app(\App\Application\Reporting\ReportRegistry::class);
        if (! $registry->has($key)) return null;
        $executor = app(\App\Application\Reporting\ReportExecutor::class);
        return $executor->execute($key, $tenantId, ReportRange::last30Days());
    }
}
