<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

class UpgradeToPro extends Page
{
    protected string $view = 'filament.app.pages.upgrade-to-pro';

    public static function getNavigationLabel(): string
    {
        return 'Mejorar a PRO';
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
                    'title' => 'WhatsApp conectado',
                    'description' => 'Boton de contacto visible para que tus clientes te escriban desde el micrositio.',
                ],
                [
                    'icon' => 'heroicon-o-cube',
                    'title' => 'Catalogo amplio',
                    'description' => 'Supera el limite Free de 10 productos y mantiene tu oferta organizada para vender con menos friccion.',
                ],
                [
                    'icon' => 'heroicon-o-calendar-days',
                    'title' => 'Reservas avanzadas',
                    'description' => 'Activa mas control operativo para agenda, servicios y seguimiento cuando el negocio ya lo necesite.',
                ],
                [
                    'icon' => 'heroicon-o-users',
                    'title' => 'Seguidores',
                    'description' => 'Consulta las personas que siguen tu negocio y da seguimiento comercial.',
                ],
                [
                    'icon' => 'heroicon-o-megaphone',
                    'title' => 'Marketing',
                    'description' => 'Organiza promociones y novedades para mantener vivo el contacto con clientes.',
                ],
                [
                    'icon' => 'heroicon-o-chart-bar',
                    'title' => 'Reportes',
                    'description' => 'Revisa senales de visitas, pedidos y rendimiento para decidir que mover primero.',
                ],
                [
                    'icon' => 'heroicon-o-sparkles',
                    'title' => 'Agente IA del negocio',
                    'description' => 'Valor PRO con activacion segura por tenant, permiso ai.use y datos siempre filtrados al negocio actual.',
                ],
            ],
            'price' => 'L. 1,500',
            'period' => 'mes',
        ];
    }
}
