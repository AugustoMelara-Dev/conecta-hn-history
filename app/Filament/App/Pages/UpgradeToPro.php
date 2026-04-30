<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Enums\TenantFeature;
use App\Infrastructure\TenantGovernance\Presentation\TenantPlanPresentationReader;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class UpgradeToPro extends Page
{
    protected string $view = 'filament.app.pages.upgrade-to-pro';

    public static function getNavigationLabel(): string
    {
        return 'Mejorar a PRO';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Cuenta';
    }

    public function getTitle(): string
    {
        return 'Mejora tu negocio a PRO';
    }

    public static function getNavigationSort(): ?int
    {
        return 100;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = self::getTenantRecord();

        return $tenant instanceof Tenant && $tenant->hasFeature(TenantFeature::UPGRADE_PROMPT);
    }

    public function getViewData(): array
    {
        $tenant = self::getTenantRecord();
        $plan = $tenant instanceof Tenant
            ? app(TenantPlanPresentationReader::class)->forTenant($tenant)
            : [
                'marketingLabel' => 'Plan PRO',
                'billingCta' => 'Activar PRO',
                'priceLabel' => 'L. 99.00/mes',
                'catalogCopy' => 'Catalogo ampliado para publicar mas productos, imagenes y merchandising.',
                'marketingCopy' => 'Suite comercial activa para QR, WhatsApp, campañas y seguimiento.',
                'brandingCopy' => 'Personalizacion visual avanzada ya habilitada para tu micrositio.',
                'followersCopy' => 'Tu audiencia ya esta disponible con detalle completo para operar seguimiento.',
            ];

        return [
            'tenant' => $tenant,
            'plan' => $plan,
            'features' => [
                [
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'title' => 'Integración con WhatsApp',
                    'description' => $plan['marketingCopy'],
                ],
                [
                    'icon' => 'heroicon-o-cube',
                    'title' => 'Catalogo ampliado',
                    'description' => $plan['catalogCopy'],
                ],
                [
                    'icon' => 'heroicon-o-paint-brush',
                    'title' => 'Marca personalizada',
                    'description' => $plan['brandingCopy'],
                ],
                [
                    'icon' => 'heroicon-o-users',
                    'title' => 'Seguidores visibles',
                    'description' => $plan['followersCopy'],
                ],
                [
                    'icon' => 'heroicon-o-megaphone',
                    'title' => 'Campanas de marketing',
                    'description' => $plan['marketingCopy'],
                ],
                [
                    'icon' => 'heroicon-o-chart-bar',
                    'title' => 'Métricas avanzadas',
                    'description' => 'Ten una lectura más completa del rendimiento y la actividad del negocio.',
                ],
            ],
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
