<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')
            ->profile()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->colors([
                'primary' => Color::Slate,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Admin\Widgets\TenantsOverviewWidget::class,
                \App\Filament\Admin\Widgets\GlobalRevenueChartWidget::class,
                \App\Filament\Admin\Widgets\PlanDistributionChartWidget::class,
                \App\Filament\Admin\Widgets\TopTenantsTableWidget::class,
                \App\Filament\Admin\Widgets\SystemHealthWidget::class,
                \App\Filament\Admin\Widgets\RecentIncidentsWidget::class,
                \App\Filament\Admin\Widgets\AiCostByTenantChartWidget::class,
                \App\Filament\Admin\Widgets\DiskAndQueueWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Plataforma'),
                NavigationGroup::make('CRM Global'),
                NavigationGroup::make('Billing'),
                NavigationGroup::make('AI'),
                NavigationGroup::make('Auditoría'),
                NavigationGroup::make('Configuración'),
            ]);
    }

    public function boot(): void
    {
        //
    }
}
