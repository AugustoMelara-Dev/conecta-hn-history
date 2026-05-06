<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * PendingReviewAlert - Alerta de Revisión Pendiente
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades.
 */
class PendingReviewAlert extends Widget
{
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

        // Show if pending, rejected, or inactive
        return in_array($tenant->status, ['pending', 'rejected']) || ! $tenant->is_active;
    }
}
