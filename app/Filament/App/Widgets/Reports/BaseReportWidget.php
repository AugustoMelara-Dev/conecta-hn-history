<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportRegistry;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

abstract class BaseReportWidget extends Widget
{
    protected static ?int $sort = 0;

    abstract public function getReportKey(): ReportKey;

    protected function getReportData(): ?ReportData
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return null;
        }
        $key = $this->getReportKey();
        $registry = app(ReportRegistry::class);
        if (! $registry->has($key)) {
            return null;
        }
        $executor = app(ReportExecutor::class);

        return $executor->execute($key, $tenantId, ReportRange::last30Days());
    }
}
