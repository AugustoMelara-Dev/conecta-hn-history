<?php

namespace App\Providers\Filament;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * AdminPanelProvider - Centro de Comando Super Admin
 *
 * Configuración del panel administrativo con:
 * - Paleta de colores azul profesional
 * - Tipografía Outfit (moderna y tecnológica)
 * - Logo vectorial de marca
 * - Navegación optimizada para productividad
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ═══════════════════════════════════════════════════════════════
            // IDENTIDAD DEL PANEL
            // ═══════════════════════════════════════════════════════════════
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web')

            // ═══════════════════════════════════════════════════════════════
            // BRANDING: Logo y Nombre
            // ═══════════════════════════════════════════════════════════════
            ->brandName('Conecta HN Admin')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            // ═══════════════════════════════════════════════════════════════
            // PALETA DE COLORES: Azul Profesional
            // ═══════════════════════════════════════════════════════════════
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])

            // ═══════════════════════════════════════════════════════════════
            // TIPOGRAFÍA: Outfit (Google Fonts)
            // ═══════════════════════════════════════════════════════════════
            ->font('Outfit')

            // ═══════════════════════════════════════════════════════════════
            // ERGONOMÍA DE NAVEGACIÓN
            // ═══════════════════════════════════════════════════════════════
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(false)
            ->maxContentWidth('full')

            // ═══════════════════════════════════════════════════════════════
            // MODO OSCURO
            // ═══════════════════════════════════════════════════════════════
            ->darkMode(true)

            // ═══════════════════════════════════════════════════════════════
            // DESCUBRIMIENTO DE RECURSOS
            // ═══════════════════════════════════════════════════════════════
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // ══════════════════════════════════════════════════════════
                // EL CEREBRO DIGITAL - Dashboard Super Admin
                // Jerarquía Visual: KPIs → Gráficos → Tabla de Acción
                // ══════════════════════════════════════════════════════════

                // 1. Bienvenida (contexto de usuario)
                \Filament\Widgets\AccountWidget::class,

                // 2. NIVEL 1: KPIs Estratégicos (MRR, Revenue, Conversión)
                \App\Filament\Widgets\FinancialStatsOverview::class,

                // 3. NIVEL 2: Análisis Temporal (Gráficos de Tendencia)
                \App\Filament\Widgets\AcquisitionGrowthChart::class,
                \App\Filament\Widgets\PlanDistributionChart::class,

                // 4. NIVEL 3: Centro de Comando (Tabla de Pagos Pendientes)
                \App\Filament\Widgets\PendingPaymentsWidget::class,
            ])

            // ═══════════════════════════════════════════════════════════════
            // MIDDLEWARE STACK
            // ═══════════════════════════════════════════════════════════════
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        Filament::serving(function () {
            // Only apply this check to the admin panel
            if (Filament::getCurrentPanel()->getId() !== 'admin') {
                return;
            }

            // Master admin always has access
            if (auth()->check() && auth()->user()->email === 'admin@conectahn.com') {
                return;
            }

            // Other admins must have super_admin role
            if (! auth()->user()?->hasRole('super_admin')) {
                abort(403, 'Acceso denegado. Se requiere rol de Super Admin.');
            }
        });
    }
}
