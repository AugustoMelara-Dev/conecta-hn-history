<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Models\Tenant;
use App\Settings\GeneralSettings;
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

        return $tenant instanceof Tenant && ! $tenant->is_pro;
    }

    public function getViewData(): array
    {
        $price = 'L. 99.00';
        $period = 'mes';

        try {
            $settings = app(GeneralSettings::class);
            $price = 'L. '.number_format((float) ($settings->pro_plan_price ?? 99), 2);
        } catch (\Throwable) {
            // Keep the page usable even if settings bootstrap is unstable locally.
        }

        return [
            'tenant' => self::getTenantRecord(),
            'features' => [
                [
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'title' => 'Integración con WhatsApp',
                    'description' => 'Botón directo para que tus clientes te escriban sin pasos extra.',
                ],
                [
                    'icon' => 'heroicon-o-cube',
                    'title' => 'Productos ilimitados',
                    'description' => 'Publica todo tu catálogo sin toparte con el límite del plan base.',
                ],
                [
                    'icon' => 'heroicon-o-paint-brush',
                    'title' => 'Marca personalizada',
                    'description' => 'Destaca tu negocio con banner, logo y una presencia pública más completa.',
                ],
                [
                    'icon' => 'heroicon-o-users',
                    'title' => 'Seguidores visibles',
                    'description' => 'Consulta quién sigue tu negocio y entiende mejor tu audiencia.',
                ],
                [
                    'icon' => 'heroicon-o-megaphone',
                    'title' => 'Campañas de marketing',
                    'description' => 'Prepara promociones y novedades para tu audiencia desde el panel.',
                ],
                [
                    'icon' => 'heroicon-o-chart-bar',
                    'title' => 'Métricas avanzadas',
                    'description' => 'Ten una lectura más completa del rendimiento y la actividad del negocio.',
                ],
            ],
            'price' => $price,
            'period' => $period,
        ];
    }

    private static function getTenantRecord(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
