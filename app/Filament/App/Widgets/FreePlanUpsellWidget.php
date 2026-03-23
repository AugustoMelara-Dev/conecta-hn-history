<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class FreePlanUpsellWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.free-plan-upsell';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return -1;
    }

    public static function canView(): bool
    {
        $tenant = self::resolveTenant();

        return $tenant instanceof Tenant && ! $tenant->getIsProAttribute();
    }

    public function getUpgradeUrl(): string
    {
        $tenant = self::resolveTenant();

        if (! $tenant instanceof Tenant) {
            return '#';
        }

        try {
            return route('filament.app.pages.manage-billing', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
        } catch (\Throwable) {
            return '#';
        }
    }

    private static function resolveTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        $user = auth()->user();
        $tenant = $user instanceof User ? $user->tenants()->first() : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
