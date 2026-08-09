<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Pages\EditTenantProfile;
use App\Filament\App\Pages\Dashboard as AppDashboard;
use App\Filament\App\Widgets\OperationalCockpitWidget;
use App\Filament\App\Widgets\OrdersChart;
use App\Filament\App\Widgets\QuickActionsWidget;
use App\Filament\App\Widgets\RevenueChart;
use App\Filament\App\Widgets\StatsOverview;
use App\Filament\App\Widgets\TenantAiAgentWidget;
use App\Http\Middleware\InitializeFilamentTenantContext;
use App\Models\Tenant;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * AppPanelProvider - Panel del Inquilino (Mobile-First) - Filament 4.
 *
 * Migrated to Filament 4 API:
 * - Schema::make() en lugar de Form::make()
 * - protected static ?string $model (nullable)
 * - databaseNotifications()
 * - viteTheme()
 * - tenantRoutePrefix(), tenantMiddleware()
 * - breadcrumbs()
 * - navigationGroups()
 *
 * @see https://filamentphp.com/docs/4.x/panels/configuration
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantProfile(EditTenantProfile::class)
            ->tenantMiddleware([
                InitializeFilamentTenantContext::class,
            ], isPersistent: true)
            ->tenantRoutePrefix(null)

            // BRANDING
            ->brandName('Dovra AI')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            // AUTH
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()

            // SPA MODE
            ->spa()

            // LAYOUT
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->topNavigation(false)
            ->breadcrumbs()

            // PALETA INDIGO
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])

            ->assets([
                Css::make(
                    'app-business-components',
                    resource_path('css/filament/app/business-components.css'),
                )
                    ->html(fn () => Vite::asset('resources/css/filament/app/business-components.css')),
            ])

            // FILAMENT 4: database notifications
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // FILAMENT 4: navigation groups explícitos
            ->navigationGroups([
                NavigationGroup::make('Operaciones')
                    ->icon('heroicon-o-briefcase')
                    ->collapsible(),
                NavigationGroup::make('CRM')
                    ->icon('heroicon-o-user-group')
                    ->collapsible(),
                NavigationGroup::make('Marketing')
                    ->icon('heroicon-o-megaphone')
                    ->collapsible(),
                NavigationGroup::make('Catálogo')
                    ->icon('heroicon-o-shopping-bag')
                    ->collapsible(),
                NavigationGroup::make('Pedidos')
                    ->icon('heroicon-o-shopping-cart')
                    ->collapsible(),
                NavigationGroup::make('Configuración')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible(),
                NavigationGroup::make('Plataforma')
                    ->icon('heroicon-o-cloud')
                    ->collapsible(),
            ])

            // DISCOVERY
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\\Filament\\App\\Widgets')

            ->pages([
                AppDashboard::class,
            ])

            // WIDGETS DEL DASHBOARD
            ->widgets([
                StatsOverview::class,
                RevenueChart::class,
                OrdersChart::class,
                QuickActionsWidget::class,
                OperationalCockpitWidget::class,
                TenantAiAgentWidget::class,
            ])

            // MIDDLEWARE
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

            // RENDER HOOKS
            ->renderHook(
                'panels::global-search.before',
                fn (): string => '',
            )

            // AVATAR / PROFILE
            ->defaultAvatarProvider(UiAvatarsProvider::class);
    }

    public function boot(): void
    {
        // Hook para registrar extensiones específicas del panel App
    }
}
