<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\OpeningHour;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;

final class Dashboard extends \Filament\Pages\Dashboard
{
    protected static bool $isDiscovered = false;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected string $view = 'filament.app.pages.dashboard';

    public string $tenantName = 'Tu negocio';

    public bool $isPro = false;

    public bool $isOpen = true;

    public int $productsCount = 0;

    public int $ordersCount = 0;

    public int $contactsCount = 0;

    public int $followersCount = 0;

    /** @var array<string, string> */
    public array $links = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->tenantName = (string) $tenant->name;
        $this->isPro = (bool) $tenant->is_pro;
        $this->productsCount = $this->relationCount($tenant, 'products');
        $this->ordersCount = $this->relationCount($tenant, 'orders');
        $this->contactsCount = $this->relationCount($tenant, 'contacts');
        $this->followersCount = $this->relationCount($tenant, 'followers');
        $this->isOpen = $this->resolveOpenStatus($tenant);

        $base = '/app/'.rawurlencode((string) $tenant->slug);
        $this->links = [
            'public' => route('tenant.public.show', ['tenant' => $tenant->slug], false),
            'products' => $base.'/products',
            'orders' => $base.'/orders',
            'contacts' => $base.'/crm/contacts',
            'reports' => $base.'/reports-overview',
            'marketing' => $base.'/manage-marketing',
            'settings' => $base.'/settings',
            'billing' => $base.'/manage-billing',
        ];
    }

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [];
    }

    private function relationCount(Tenant $tenant, string $relation): int
    {
        try {
            $related = $tenant->{$relation}();

            return $related instanceof Relation ? $related->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function resolveOpenStatus(Tenant $tenant): bool
    {
        try {
            $now = now(OpeningHour::BUSINESS_TIMEZONE);
            $hours = OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('day_of_week', $now->dayOfWeek)
                ->get();

            return $hours->isEmpty() || $hours->contains(
                fn (OpeningHour $openingHour): bool => $openingHour->isOpenAt($now),
            );
        } catch (\Throwable) {
            return true;
        }
    }
}
