<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class AccountCompletenessWidget extends Widget
{
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

    /**
     * @return array{percentage: int, missingItems: list<array<string, mixed>>, completedItems: list<array<string, mixed>>, isComplete: bool, tenant: Tenant|null}
     */
    protected function getViewData(): array
    {
        $tenant = $this->getTenantRecord();

        if (! $tenant instanceof Tenant) {
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

    /**
     * @return array{percentage: int, completed: list<array<string, mixed>>, missing: list<array<string, mixed>>}
     */
    protected function calculateCompleteness(Tenant $tenant): array
    {
        $tenantSlug = (string) ($tenant->getAttribute('slug') ?? '');
        $description = (string) ($tenant->getAttribute('description') ?? '');

        $checks = [
            [
                'field' => 'Logo',
                'check' => ! empty($tenant->getAttribute('logo_path')),
                'icon' => 'camara',
                'action' => 'Sube tu logo',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Banner',
                'check' => ! empty($tenant->getAttribute('banner_path')),
                'icon' => 'imagen',
                'action' => 'Agrega portada',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Descripcion',
                'check' => $description !== '' && strlen($description) > 30,
                'icon' => 'nota',
                'action' => 'Escribe descripcion',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'WhatsApp',
                'check' => ! empty($tenant->getAttribute('whatsapp_number')),
                'icon' => 'telefono',
                'action' => 'Agrega WhatsApp',
                'url' => "/app/{$tenantSlug}/edit-tenant-profile",
            ],
            [
                'field' => 'Productos',
                'check' => $tenant->products()->count() > 0,
                'icon' => 'bolsa',
                'action' => 'Agrega producto',
                'url' => "/app/{$tenantSlug}/products/create",
            ],
        ];

        $completed = array_values(array_filter($checks, fn (array $check): bool => (bool) $check['check']));
        $missing = array_values(array_filter($checks, fn (array $check): bool => ! $check['check']));
        $percentage = (int) round((count($completed) / count($checks)) * 100);

        return [
            'percentage' => $percentage,
            'completed' => $completed,
            'missing' => $missing,
        ];
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
