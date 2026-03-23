<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\ProductResource;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * ProductLimitWarning - Banner de Advertencia de Límite
 */
class ProductLimitWarning extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.product-limit-warning';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 5;
    }

    public static function canView(): bool
    {
        $info = ProductResource::getProductLimitInfo();
        if ($info['isPro'] ?? false) {
            return false;
        }

        return ($info['percentage'] ?? 0) >= 80;
    }

    protected function getViewData(): array
    {
        return [
            ...ProductResource::getProductLimitInfo(),
            'upgradeUrl' => $this->getUpgradeUrl(),
        ];
    }

    private function getUpgradeUrl(): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return '#';
        }

        try {
            return route('filament.app.pages.manage-billing', ['tenant' => (string) ($tenant->getAttribute('slug') ?? '')]);
        } catch (\Throwable) {
            return '#';
        }
    }
}
