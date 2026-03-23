<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\OpeningHour;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.quick-actions-widget';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 0;
    }

    /**
     * @return array{tenant: Tenant|null, isOpen: bool, productsCount: int, createProductUrl: string, profileUrl: string, publicUrl: string}
     */
    protected function getViewData(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return [
                'tenant' => null,
                'isOpen' => false,
                'productsCount' => 0,
                'createProductUrl' => '#',
                'profileUrl' => '#',
                'publicUrl' => '#',
            ];
        }

        return [
            'tenant' => $tenant,
            'isOpen' => $this->isBusinessOpen($tenant),
            'productsCount' => $tenant->products()->count(),
            'createProductUrl' => $this->getCreateProductUrl($tenant),
            'profileUrl' => $this->getProfileUrl($tenant),
            'publicUrl' => $this->getPublicUrl($tenant),
        ];
    }

    protected function isBusinessOpen(Tenant $tenant): bool
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

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function getCreateProductUrl(Tenant $tenant): string
    {
        try {
            return route('filament.app.resources.products.create', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function getProfileUrl(Tenant $tenant): string
    {
        try {
            return route('filament.app.tenant.profile', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function getPublicUrl(Tenant $tenant): string
    {
        try {
            return route('tenant.microsite.show', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
        } catch (\Throwable) {
            return '#';
        }
    }
}
