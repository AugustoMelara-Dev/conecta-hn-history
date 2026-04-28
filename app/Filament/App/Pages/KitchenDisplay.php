<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Operations\KitchenDisplayReadModel;
use App\Domain\Access\Enums\Permission;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class KitchenDisplay extends Page
{
    protected string $view = 'filament.app.pages.kitchen-display';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static ?int $navigationSort = 6;

    /**
     * @var array{
     *   enabled: bool,
     *   reason: string,
     *   columns: array<string, list<array<string, mixed>>>
     * }
     */
    public array $board = [
        'enabled' => false,
        'reason' => '',
        'columns' => [],
    ];

    public static function getNavigationLabel(): string
    {
        return 'Cocina';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Operaciones';
    }

    public function getTitle(): string
    {
        return 'Kitchen display';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantPermissionGuardInterface::class)->allows(
            $user,
            $tenant,
            Capability::KITCHEN_DISPLAY,
            Permission::ORDERS_MANAGE,
        );
    }

    public function mount(KitchenDisplayReadModel $readModel): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->board = $readModel->forTenant($tenant);
    }
}
