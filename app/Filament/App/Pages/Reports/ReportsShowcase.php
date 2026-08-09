<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportExporter;
use App\Application\Reporting\ReportRegistry;
use App\Domain\Access\Enums\Permission;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class ReportsShowcase extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $title = 'Detalle de reporte';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Detalle';

    protected static ?int $navigationSort = 51;

    protected string $view = 'filament.app.pages.reports.reports-showcase';

    public ?string $reportKey = null;

    public ?string $range = '30d';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant
            && app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW);
    }

    public function mount(?string $reportKey = null): void
    {
        $this->reportKey = $reportKey ?? request()->query('key');
    }

    public function getViewData(): array
    {
        $key = ReportKey::tryFrom($this->reportKey ?? '');
        $registry = app(ReportRegistry::class);
        if ($key === null || ! $registry->has($key)) {
            return ['report' => null, 'data' => null];
        }

        $report = $registry->get($key);
        $tenantId = Filament::getTenant()?->getKey();
        if (! $tenantId) {
            return ['report' => $report, 'data' => null];
        }

        $data = app(ReportExecutor::class)->execute($key, (string) $tenantId, $this->reportRange());

        return ['report' => $report, 'data' => $data->toArray()];
    }

    public function export(string $format): mixed
    {
        $key = ReportKey::tryFrom($this->reportKey ?? '');
        $reportFormat = ReportFormat::tryFrom($format);
        $registry = app(ReportRegistry::class);
        if ($key === null || $reportFormat === null || ! $registry->has($key)) {
            return null;
        }

        $user = auth()->user();
        $tenant = Filament::getTenant();
        if (! $user instanceof User || ! $tenant instanceof Tenant
            || ! app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_EXPORT)) {
            return null;
        }

        try {
            $path = app(ReportExporter::class)->export(
                $registry->get($key),
                (string) $tenant->getKey(),
                $this->reportRange(),
                $reportFormat,
            );

            return response()->download($path);
        } catch (Throwable) {
            Notification::make()->title('No pudimos exportar el reporte.')->danger()->send();

            return null;
        }
    }

    private function reportRange(): ReportRange
    {
        $preset = in_array($this->range, ['today', 'yesterday', '7d', '30d', 'qtd', 'ytd'], true) ? $this->range : '30d';

        return ReportRange::fromPreset($preset ?? '30d');
    }
}
