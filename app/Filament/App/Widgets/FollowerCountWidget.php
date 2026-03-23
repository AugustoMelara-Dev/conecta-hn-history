<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * FollowerCountWidget - Contador de Seguidores
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades.
 */
class FollowerCountWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.follower-count';

    public function getColumnSpan(): int|string|array
    {
        return 1;
    }

    public static function getSort(): int
    {
        return 10;
    }

    /**
     * @return array{count: int, isPro: bool}
     */
    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return ['count' => 0, 'isPro' => false];
        }

        return [
            'count' => $tenant->followers()->count(),
            'isPro' => $tenant->getIsProAttribute(),
        ];
    }
}
