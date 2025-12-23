<?php

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * AccountCompletenessWidget - Gamificación de Completitud del Perfil
 *
 * Filament v4 Compatible: Usa métodos en lugar de propiedades estáticas.
 */
class AccountCompletenessWidget extends Widget
{
    /**
     * Filament v4: Método para la vista
     */
    public function getView(): string
    {
        return 'filament.app.widgets.account-completeness-widget';
    }

    /**
     * Filament v4: Método dinámico para column span
     */
    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    /**
     * Filament v4: Método estático para ordenamiento
     */
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
                'icon' => '📷',
                'action' => 'Sube tu logo',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Banner',
                'check' => ! empty($tenant->banner_path),
                'icon' => '🖼️',
                'action' => 'Agrega portada',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Descripción',
                'check' => ! empty($tenant->description) && strlen($tenant->description) > 30,
                'icon' => '📝',
                'action' => 'Escribe descripción',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'WhatsApp',
                'check' => ! empty($tenant->whatsapp_number),
                'icon' => '📱',
                'action' => 'Agrega WhatsApp',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Productos',
                'check' => $tenant->products()->count() > 0,
                'icon' => '🛍️',
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
