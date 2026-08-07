<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use Filament\Widgets\Widget;

class FreePlanUpsellWidget extends Widget
{
    protected string $view = 'filament.widgets.free-plan-upsell';

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
        $tenant = auth()->user()?->tenants()->first();

        return $tenant && ! $tenant->is_pro;
    }

    public function getUpgradeUrl(): string
    {
        $tenant = auth()->user()?->tenants()->first();

        if ($tenant) {
            return route('filament.app.pages.manage-billing', ['tenant' => $tenant->slug]);
        }

        return url('/app');
    }
}
