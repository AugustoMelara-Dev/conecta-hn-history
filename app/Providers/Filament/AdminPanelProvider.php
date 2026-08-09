<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Admin\Widgets\AiCostByTenantChartWidget;
use App\Filament\Admin\Widgets\DiskAndQueueWidget;
use App\Filament\Admin\Widgets\GlobalRevenueChartWidget;
use App\Filament\Admin\Widgets\PlanDistributionChartWidget;
use App\Filament\Admin\Widgets\RecentIncidentsWidget;
use App\Filament\Admin\Widgets\SystemHealthWidget;
use App\Filament\Admin\Widgets\TenantsOverviewWidget;
use App\Filament\Admin\Widgets\TopTenantsTableWidget;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * AdminPanelProvider - Centro de Comando Super Admin - Filament 4.
 *
 * Migrated to Filament 4 API:
 * - Schema::make() en lugar de Form::make()
 * - protected static ?string $model (nullable)
 * - databaseNotifications()
 * - viteTheme()
 * - breadcrumbs()
 * - navigationGroups() con iconos
 *
 * @see https://filamentphp.com/docs/4.x/panels/configuration
 */
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

            // BRANDING
            ->brandName('Dovra AI Admin')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            // PROFILE
            ->profile()

            // SPA MODE
            ->spa()

            // LAYOUT
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->breadcrumbs()

            // PALETA CORPORATE MINIMALIST (Zinc primary, Slate gray)
            ->colors([
                'primary' => Color::Slate,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])


            // FILAMENT 4: database notifications
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // FILAMENT 4: navigation groups
            ->navigationGroups([
                NavigationGroup::make('Plataforma')
                    ->icon('heroicon-o-server-stack')
                    ->collapsible(),
                NavigationGroup::make('CRM Global')
                    ->icon('heroicon-o-globe-alt')
                    ->collapsible(),
                NavigationGroup::make('Billing')
                    ->icon('heroicon-o-banknotes')
                    ->collapsible(),
                NavigationGroup::make('AI')
                    ->icon('heroicon-o-sparkles')
                    ->collapsible(),
                NavigationGroup::make('Auditoría')
                    ->icon('heroicon-o-shield-check')
                    ->collapsible(),
                NavigationGroup::make('Configuración')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible(),
            ])

            // DISCOVERY
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

            ->pages([
                Dashboard::class,
            ])

            ->widgets([
                TenantsOverviewWidget::class,
                GlobalRevenueChartWidget::class,
                PlanDistributionChartWidget::class,
                TopTenantsTableWidget::class,
                SystemHealthWidget::class,
                RecentIncidentsWidget::class,
                AiCostByTenantChartWidget::class,
                DiskAndQueueWidget::class,
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

            // RENDER HOOKS para global search y notificaciones
            ->renderHook(
                'panels::global-search.before',
                fn (): string => '',
            )

            // AVATAR
            ->defaultAvatarProvider(UiAvatarsProvider::class);
    }

    public function boot(): void
    {
        // Hook para extensiones específicas del admin panel
    }
}
