<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ReportRegistry;
use App\Domain\Access\Enums\Permission;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class ReportsCompare extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $title = 'Comparar reportes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operaciones';

    protected static ?string $navigationLabel = 'Comparar';

    protected static ?int $navigationSort = 54;

    protected string $view = 'filament.app.pages.reports.reports-compare';

    public ?string $primaryKey = null;

    public ?string $secondaryKey = null;

    public ?string $range = '30d';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Tenant
            && app(TenantPermissionGuardInterface::class)->allows($user, $tenant, Capability::ANALYTICS_BASIC, Permission::ANALYTICS_VIEW);
    }

    public function getViewData(): array
    {
        $registry = app(ReportRegistry::class);
        $reports = $registry->all();
        $primary = ReportKey::tryFrom($this->primaryKey ?? '');
        $secondary = ReportKey::tryFrom($this->secondaryKey ?? '');
        $tenantId = Filament::getTenant()?->getKey();

        if ($primary === null || $secondary === null || $primary === $secondary || ! $tenantId
            || ! $registry->has($primary) || ! $registry->has($secondary)) {
            return ['reports' => $reports, 'comparison' => null];
        }

        $preset = in_array($this->range, ['today', 'yesterday', '7d', '30d', 'qtd', 'ytd'], true) ? $this->range : '30d';
        $range = ReportRange::fromPreset($preset ?? '30d');
        $executor = app(ReportExecutor::class);

        return ['reports' => $reports, 'comparison' => [
            'primary' => $registry->get($primary),
            'primary_data' => $executor->execute($primary, (string) $tenantId, $range)->toArray(),
            'secondary' => $registry->get($secondary),
            'secondary_data' => $executor->execute($secondary, (string) $tenantId, $range)->toArray(),
        ]];
    }
}
