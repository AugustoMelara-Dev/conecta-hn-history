<?php

namespace App\Filament\App\Widgets;

use Filament\Widgets\Widget;

/**
 * FollowerCountWidget - Contador de Seguidores
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades.
 */
class FollowerCountWidget extends Widget
{
    public function getView(): string
    {
        return 'filament.app.widgets.follower-count';
    }

    public function getColumnSpan(): int|string|array
    {
        return 1;
    }

    public static function getSort(): int
    {
        return 10;
    }
}

