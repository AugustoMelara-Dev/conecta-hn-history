<?php

namespace App\Filament\App\Widgets;

use Filament\Widgets\Widget;

/**
 * FreePlanUpsellWidget - Banner de Upsell para usuarios Free
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades.
 */
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
        return -1; // Show at the very top
    }

    public static function canView(): bool
    {
        $tenant = auth()->user()?->tenants()->first();

        return $tenant && ! $tenant->is_pro;
    }

    public function getUpgradeUrl(): string
    {
        $tenant = auth()->user()?->tenants()->first();
        if ($tenant) {
            return "/app/{$tenant->slug}/billing";
        }

        return '#';
    }
}

