<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\OpeningHour;
use App\Models\Tenant;
use Filament\Facades\Filament;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.app.pages.dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    public string $tenantName = 'Tu negocio';

    public bool $isPro = false;

    public bool $isOpen = true;

    public int $productsCount = 0;

    public int $activeProductsCount = 0;

    public int $sectionsCount = 0;

    public int $followersCount = 0;

    public bool $hasWhatsApp = false;

    public bool $hasSchedule = false;

    public string $publicUrl = '#';

    public string $productsUrl = '#';

    public string $profileUrl = '#';

    public string $marketingUrl = '#';

    public string $billingUrl = '#';

    public function getWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $attributes = $tenant->getAttributes();
        $tenantSlug = (string) ($attributes['slug'] ?? '');
        $this->tenantName = (string) ($attributes['name'] ?? 'Tu negocio');
        $this->isPro = (bool) $tenant->is_pro;

        try {
            $this->productsCount = $tenant->products()->count();
            $this->activeProductsCount = $tenant->products()->where('is_active', true)->count();
            $this->sectionsCount = $tenant->menuSections()->count();
            $this->followersCount = $tenant->followers()->count();
        } catch (\Throwable) {
            // Keep dashboard resilient even when optional modules are unstable.
        }

        $this->hasWhatsApp = filled($attributes['whatsapp_number'] ?? null);
        $this->hasSchedule = filled($attributes['simple_schedule'] ?? null) || $this->tenantHasOpeningHours($tenant);
        $this->isOpen = $this->resolveOpenStatus($tenant);

        $this->publicUrl = $this->safeRoute('tenant.microsite.show', ['tenant' => $tenantSlug]);
        $this->productsUrl = $this->safeRoute('filament.app.resources.products.index', ['tenant' => $tenantSlug]);
        $this->profileUrl = $this->safeRoute('filament.app.pages.settings', ['tenant' => $tenantSlug]);
        $this->marketingUrl = $this->safeRoute('filament.app.pages.manage-marketing', ['tenant' => $tenantSlug]);
        $this->billingUrl = $this->safeRoute('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]);
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function tenantHasOpeningHours(Tenant $tenant): bool
    {
        try {
            return OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveOpenStatus(Tenant $tenant): bool
    {
        try {
            $now = now(OpeningHour::BUSINESS_TIMEZONE);

            $todayHours = OpeningHour::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenant->getKey())
                ->where('day_of_week', $now->dayOfWeek)
                ->get();

            if ($todayHours->isEmpty()) {
                return true;
            }

            return $todayHours->contains(
                fn (OpeningHour $openingHour): bool => $openingHour->isOpenAt($now)
            );
        } catch (\Throwable) {
            return true;
        }
    }

    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return '#';
        }
    }
}
