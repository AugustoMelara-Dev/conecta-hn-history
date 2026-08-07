<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

/**
 * PendingReviewAlert - Alerta de Revisión Pendiente
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades.
 */
class PendingReviewAlert extends Widget
{
    protected string $view = 'filament.app.widgets.pending-review-alert';

    public function getView(): string
    {
        return 'filament.app.widgets.pending-review-alert';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return -10;
    }

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        $isPendingOrRejected = Schema::hasColumn($tenant->getTable(), 'status')
            && in_array($tenant->getAttribute('status'), ['pending', 'rejected'], true);

        $isInactive = Schema::hasColumn($tenant->getTable(), 'is_active')
            && ! (bool) $tenant->getAttribute('is_active');

        return $isPendingOrRejected || $isInactive;
    }
}
