<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Reporting\ReportRegistry;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class ReportsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $title = 'Reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.app.pages.reports.reports-overview';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant
            && app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW);
    }

    public function getViewData(): array
    {
        return ['reportsByCategory' => app(ReportRegistry::class)->grouped()];
    }
}
