<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\Widget;

class FreePlanUpsellWidget extends Widget
{
    public function getView(): string
    {
        return 'filament.widgets.free-plan-upsell';
    }

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

        return '/app/'.(string) ($tenant->getAttribute('slug') ?? '').'/billing';
    }

    private static function resolveTenant(): ?Tenant
    {
        $user = auth()->user();
        $tenant = $user instanceof User ? $user->tenants()->first() : null;

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
