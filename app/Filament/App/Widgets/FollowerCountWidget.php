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
    protected string $view = 'filament.app.widgets.follower-count';

    public int $followersCount = 0;

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        try {
            $this->followersCount = $tenant->followers()->count();
        } catch (\Throwable) {
            $this->followersCount = 0;
        }
    }

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
