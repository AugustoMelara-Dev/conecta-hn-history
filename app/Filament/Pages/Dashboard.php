<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected string $view = 'filament.pages.dashboard';

    public int $tenantsCount = 0;

    public int $proTenantsCount = 0;

    public int $usersCount = 0;

    public int $pendingPaymentsCount = 0;

    /** @var list<array{business: string, owner: string, status: string, created_at: string}> */
    public array $recentBusinesses = [];

    public function mount(): void
    {
        $this->tenantsCount = Tenant::query()->count();
        $this->proTenantsCount = Tenant::query()->where('is_pro', true)->count();
        $this->usersCount = User::query()->count();
        $this->pendingPaymentsCount = SubscriptionPayment::query()
            ->withoutGlobalScopes()
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->count();

        $this->recentBusinesses = Tenant::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Tenant $tenant): array {
                return [
                    'business' => (string) ($tenant->name ?? 'Negocio sin nombre'),
                    'owner' => (string) ($tenant->user?->email ?? $tenant->user?->name ?? 'Sin propietario'),
                    'status' => $tenant->is_pro ? 'PRO' : 'Base',
                    'created_at' => optional($tenant->created_at)?->format('d/m/Y H:i') ?? 'Sin fecha',
                ];
            })
            ->all();
    }

    public function getWidgets(): array
    {
        return [];
    }
}
