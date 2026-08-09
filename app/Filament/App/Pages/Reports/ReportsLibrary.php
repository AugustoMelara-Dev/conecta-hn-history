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

class ReportsLibrary extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $title = 'Biblioteca de reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Biblioteca';

    protected static ?int $navigationSort = 53;

    protected string $view = 'filament.app.pages.reports.reports-library';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant
            && app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW);
    }

    public function getViewData(): array
    {
        return ['reports' => app(ReportRegistry::class)->all()];
    }
}
