<?php

namespace App\Filament\App\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class UpgradeToPro extends Page
{
    // Navigation Icon removed to fix type error in Filament v4

    protected string $view = 'filament.app.pages.upgrade-to-pro';

    public static function getNavigationLabel(): string
    {
        return 'Mejorar a PRO';
    }

    public function getTitle(): string
    {
        return 'Mejora tu Negocio a PRO';
    }

    public static function getNavigationSort(): ?int
    {
        return 100;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant && ! $tenant->is_pro;
    }

    public function getViewData(): array
    {
        return [
            'tenant' => Filament::getTenant(),
            'features' => [
                [
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'title' => 'Integración WhatsApp',
                    'description' => 'Botón de contacto directo para que tus clientes te escriban al instante.',
                ],
                [
                    'icon' => 'heroicon-o-cube',
                    'title' => 'Productos Ilimitados',
                    'description' => 'Sin límite de 10 productos. Muestra todo tu catálogo completo.',
                ],
                [
                    'icon' => 'heroicon-o-paint-brush',
                    'title' => 'Marca Personalizada',
                    'description' => 'Colores de tu marca, banner personalizado y logo destacado.',
                ],
                [
                    'icon' => 'heroicon-o-users',
                    'title' => 'Ver Seguidores',
                    'description' => 'Conoce quiénes siguen tu negocio y construye tu comunidad.',
                ],
                [
                    'icon' => 'heroicon-o-megaphone',
                    'title' => 'Campañas de Marketing',
                    'description' => 'Envía promociones y novedades a todos tus seguidores.',
                ],
                [
                    'icon' => 'heroicon-o-chart-bar',
                    'title' => 'Estadísticas Avanzadas',
                    'description' => 'Métricas detalladas de visitas y rendimiento de tu negocio.',
                ],
            ],
            'price' => 'L. 1,500',
            'period' => 'mes',
        ];
    }
}
