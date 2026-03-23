<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class AccountCompletenessWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.account-completeness-widget';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public static function getSort(): int
    {
        return -10;
    }

    /**
     * @return array{percentage: int, missingItems: list<array<string, mixed>>, completedItems: list<array<string, mixed>>, isComplete: bool, totalChecks: int, tenant: Tenant|null}
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
                'totalChecks' => 0,
                'tenant' => null,
            ];
        }

        $completenessData = $this->calculateCompleteness($tenant);

        return [
            'percentage' => $completenessData['percentage'],
            'missingItems' => $completenessData['missing'],
            'completedItems' => $completenessData['completed'],
            'isComplete' => $completenessData['percentage'] >= 100,
            'totalChecks' => $completenessData['total'],
            'tenant' => $tenant,
        ];
    }

    /**
     * @return array{percentage: int, completed: list<array<string, mixed>>, missing: list<array<string, mixed>>, total: int}
     */
    protected function calculateCompleteness(Tenant $tenant): array
    {
        $tenantSlug = (string) ($tenant->getAttribute('slug') ?? '');
        $description = (string) ($tenant->getAttribute('description') ?? '');
        $profileUrl = $this->getTenantProfileUrl($tenantSlug);
        $createProductUrl = $this->getCreateProductUrl($tenantSlug);
        $hasMediaTable = $this->hasMediaTable();
        $hasLogo = ($hasMediaTable && $tenant->getFirstMediaUrl('logo') !== '')
            || ! empty($tenant->getAttribute('logo_path'));
        $hasCover = ($hasMediaTable && $tenant->getFirstMediaUrl('cover') !== '')
            || ($hasMediaTable && $tenant->getFirstMediaUrl('hero') !== '')
            || ! empty($tenant->getAttribute('cover_path'))
            || ! empty($tenant->getAttribute('hero_image'));

        $checks = [
            [
                'field' => 'Logo',
                'check' => $hasLogo,
                'icon' => 'heroicon-o-camera',
                'action' => 'Sube tu logo',
                'url' => $profileUrl,
            ],
            [
                'field' => 'Banner',
                'check' => $hasCover,
                'icon' => 'heroicon-o-photo',
                'action' => 'Agrega portada',
                'url' => $profileUrl,
            ],
            [
                'field' => 'Descripcion',
                'check' => $description !== '' && strlen($description) > 30,
                'icon' => 'heroicon-o-document-text',
                'action' => 'Escribe descripcion',
                'url' => $profileUrl,
            ],
            [
                'field' => 'WhatsApp',
                'check' => ! empty($tenant->getAttribute('whatsapp_number')),
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'action' => 'Agrega WhatsApp',
                'url' => $profileUrl,
            ],
            [
                'field' => 'Productos',
                'check' => $tenant->products()->exists(),
                'icon' => 'heroicon-o-shopping-bag',
                'action' => 'Agrega producto',
                'url' => $createProductUrl,
            ],
        ];

        $completed = array_values(array_filter($checks, fn (array $check): bool => (bool) $check['check']));
        $missing = array_values(array_filter($checks, fn (array $check): bool => ! $check['check']));
        $percentage = (int) round((count($completed) / count($checks)) * 100);

        return [
            'percentage' => $percentage,
            'completed' => $completed,
            'missing' => $missing,
            'total' => count($checks),
        ];
    }

    private function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function getTenantProfileUrl(string $tenantSlug): string
    {
        try {
            return route('filament.app.tenant.profile', ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function getCreateProductUrl(string $tenantSlug): string
    {
        try {
            return route('filament.app.resources.products.create', ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function hasMediaTable(): bool
    {
        static $hasMediaTable = null;

        if ($hasMediaTable !== null) {
            return $hasMediaTable;
        }

        try {
            $hasMediaTable = Schema::hasTable('media');
        } catch (\Throwable) {
            $hasMediaTable = false;
        }

        return $hasMediaTable;
    }
}
