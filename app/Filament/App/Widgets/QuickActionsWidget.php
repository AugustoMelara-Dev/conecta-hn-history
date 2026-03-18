<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
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

    /**
     * @return array{tenant: Tenant|null, isOpen: bool, productsCount: int}
     */
    protected function getViewData(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
            return ['tenant' => null, 'isOpen' => false, 'productsCount' => 0];
        }

        return [
            'tenant' => $tenant,
            'isOpen' => $this->isBusinessOpen($tenant),
            'productsCount' => $tenant->products()->count(),
        ];
    }

    protected function isBusinessOpen(Tenant $tenant): bool
    {
        $hoursData = $tenant->getAttribute('hours_data');

        if (empty($hoursData)) {
            return true;
        }

        $hours = is_array($hoursData) ? $hoursData : json_decode((string) $hoursData, true);

        if (! is_array($hours) || $hours === []) {
            return true;
        }

        $dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $today = $dayNames[now()->dayOfWeek] ?? 'lunes';
        $currentTime = now()->format('H:i');

        if (! isset($hours[$today]) || ! is_array($hours[$today])) {
            return true;
        }

        $todayHours = $hours[$today];
        if (($todayHours['closed'] ?? false) === true) {
            return false;
        }

        $open = (string) ($todayHours['open'] ?? $todayHours['open_time'] ?? '08:00');
        $close = (string) ($todayHours['close'] ?? $todayHours['close_time'] ?? '18:00');

        return $currentTime >= $open && $currentTime <= $close;
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
