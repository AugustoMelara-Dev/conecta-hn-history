<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * QuickActionsWidget - Acciones Rápidas del Dashboard
 */
class QuickActionsWidget extends Widget
{
    protected static string $view = 'filament.app.widgets.quick-actions-widget';

    public function getView(): string
    {
        return 'filament.app.widgets.quick-actions-widget';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 0;
    }

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return ['tenant' => null, 'isOpen' => false, 'productsCount' => 0];
        }

        return [
            'tenant' => $tenant,
            'isOpen' => $this->isBusinessOpen($tenant),
            'productsCount' => $tenant->products()->count(),
        ];
    }

    protected function isBusinessOpen($tenant): bool
    {
        if (empty($tenant->hours_data)) {
            return true;
        }

        $hours = is_array($tenant->hours_data)
            ? $tenant->hours_data
            : json_decode($tenant->hours_data, true);

        if (! $hours) {
            return true;
        }

        $dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $today = $dayNames[now()->dayOfWeek] ?? 'lunes';
        $currentTime = now()->format('H:i');

        if (! isset($hours[$today])) {
            return true;
        }

        $todayHours = $hours[$today];
        if (! empty($todayHours['closed'])) {
            return false;
        }

        $open = $todayHours['open'] ?? $todayHours['open_time'] ?? '08:00';
        $close = $todayHours['close'] ?? $todayHours['close_time'] ?? '18:00';

        return $currentTime >= $open && $currentTime <= $close;
    }
}
