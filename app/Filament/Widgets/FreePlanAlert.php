<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class FreePlanAlert extends Widget
{
    public static function getView(): string
    {
        return 'filament.widgets.free-plan-alert';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function canView(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $tenant = $user->tenants->first();

        // Only show if tenant exists and is NOT pro
        return $tenant && ! $tenant->is_pro;
    }

    public function getViewData(): array
    {
        $tenant = Auth::user()->tenants->first();
        $productCount = $tenant ? $tenant->products()->count() : 0;
        $limit = 10;
        $percentage = min(100, ($productCount / $limit) * 100);

        return [
            'productCount' => $productCount,
            'limit' => $limit,
            'percentage' => $percentage,
            'isLimitReached' => $productCount >= $limit,
        ];
    }
}
