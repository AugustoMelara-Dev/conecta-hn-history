<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\ProductResource;
use Filament\Widgets\Widget;

/**
 * ProductLimitWarning - Banner de Advertencia de Límite
 */
class ProductLimitWarning extends Widget
{
    public function getView(): string
    {
        return 'filament.app.widgets.product-limit-warning';
    }

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
        if ($info['isPro'] ?? false) return false;
        return ($info['percentage'] ?? 0) >= 80;
    }

    protected function getViewData(): array
    {
        return ProductResource::getProductLimitInfo();
    }
}
