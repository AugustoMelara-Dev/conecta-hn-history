<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Interaction;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class WhatsAppClicksChart extends ChartWidget
{
    protected ?string $heading = 'Clics en WhatsApp (Ultimos 7 dias)';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return 15;
    }

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $tenantId = (string) $tenant->getKey();
        $data = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $data[] = Interaction::query()
                ->where('tenant_id', $tenantId)
                ->where('type', 'whatsapp_click')
                ->whereDate('created_at', $date->toDateString())
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Clics WhatsApp',
                    'data' => $data,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
