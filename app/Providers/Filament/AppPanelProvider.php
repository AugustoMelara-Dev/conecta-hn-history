<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\InitializeFilamentTenantContext;
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

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->tenant(\App\Models\Tenant::class, slugAttribute: 'slug')
            ->tenantProfile(\App\Filament\App\Pages\EditTenantProfile::class)
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->topNavigation(false)
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                \App\Filament\App\Widgets\StatsOverview::class,
                \App\Filament\App\Widgets\RevenueChart::class,
                \App\Filament\App\Widgets\OrdersChart::class,
                \App\Filament\App\Widgets\QuickActionsWidget::class,
                \App\Filament\App\Widgets\OperationalCockpitWidget::class,
                \App\Filament\App\Widgets\TenantAiAgentWidget::class,
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
                NavigationGroup::make('Operaciones'),
                NavigationGroup::make('CRM'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Catálogo'),
                NavigationGroup::make('Pedidos'),
                NavigationGroup::make('Configuración'),
                NavigationGroup::make('Plataforma'),
            ]);
    }

    public function boot(): void
    {
        //
    }
}
