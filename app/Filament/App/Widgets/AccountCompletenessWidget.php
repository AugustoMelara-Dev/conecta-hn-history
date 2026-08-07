<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * AccountCompletenessWidget - Perfil operativo del negocio.
 */
class AccountCompletenessWidget extends Widget
{
    protected string $view = 'filament.app.widgets.account-completeness-widget';

    public function getView(): string
    {
        return 'filament.app.widgets.account-completeness-widget';
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return -10;
    }

    protected function getViewData(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return [
                'percentage' => 0,
                'missingItems' => [],
                'completedItems' => [],
                'isComplete' => false,
                'tenant' => null,
            ];
        }

        $completenessData = $this->calculateCompleteness($tenant);

        return [
            'percentage' => $completenessData['percentage'],
            'missingItems' => $completenessData['missing'],
            'completedItems' => $completenessData['completed'],
            'isComplete' => $completenessData['percentage'] >= 100,
            'tenant' => $tenant,
        ];
    }

    protected function calculateCompleteness(Tenant $tenant): array
    {
        $tenantSlug = $tenant->slug;

        $checks = [
            [
                'field' => 'Logo',
                'check' => ! empty($tenant->logo_path),
                'action' => 'Sube tu logo',
                'url' => "/app/{$tenantSlug}/settings",
            ],
            [
                'field' => 'Portada',
                'check' => ! empty($tenant->banner_path),
                'action' => 'Agrega portada',
                'url' => "/app/{$tenantSlug}/settings",
            ],
            [
                'field' => 'Descripcion',
                'check' => ! empty($tenant->description) && strlen($tenant->description) > 30,
                'action' => 'Escribe descripcion',
                'url' => "/app/{$tenantSlug}/settings",
            ],
            [
                'field' => 'WhatsApp',
                'check' => ! empty($tenant->whatsapp_number),
                'action' => 'Agrega WhatsApp',
                'url' => "/app/{$tenantSlug}/settings",
            ],
            [
                'field' => 'Productos',
                'check' => $tenant->products()->count() > 0,
                'action' => 'Agrega producto',
                'url' => "/app/{$tenantSlug}/products/create",
            ],
        ];

        $completed = array_filter($checks, fn ($c) => $c['check']);
        $missing = array_filter($checks, fn ($c) => ! $c['check']);

        $percentage = count($checks) > 0
            ? round((count($completed) / count($checks)) * 100)
            : 0;

        return [
            'percentage' => $percentage,
            'completed' => array_values($completed),
            'missing' => array_values($missing),
        ];
    }
}
