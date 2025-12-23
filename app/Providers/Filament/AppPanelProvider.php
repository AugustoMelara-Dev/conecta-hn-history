<?php

namespace App\Providers\Filament;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
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
 * AppPanelProvider - Panel del Inquilino (Mobile-First)
 *
 * Arquitectura optimizada para:
 * - 90% de interacciones desde móviles Android gama media
 * - Conexiones a internet intermitentes
 * - Experiencia de aplicación nativa
 *
 * Diferenciación Visual:
 * - Color Indigo (vs Blue del Admin) para clara distinción
 * - Tipografía Inter para legibilidad móvil
 * - Logo vectorial compartido con brand.blade.php
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ═══════════════════════════════════════════════════════════════
            // IDENTIDAD DEL PANEL
            // ═══════════════════════════════════════════════════════════════
            ->id('app')
            ->path('app')

            // ═══════════════════════════════════════════════════════════════
            // BRANDING: Logo Vectorial y Nombre
            // ═══════════════════════════════════════════════════════════════
            ->brandName('Conecta HN')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            // ═══════════════════════════════════════════════════════════════
            // AUTENTICACIÓN
            // Deshabilitamos registro nativo - usamos wizard público
            // ═══════════════════════════════════════════════════════════════
            ->login()
            // ->registration() // Disabled - using /registrar-negocio wizard
            ->passwordReset()
            ->emailVerification()

            // ═══════════════════════════════════════════════════════════════
            // MULTI-TENANCY
            // ═══════════════════════════════════════════════════════════════
            ->tenant(\App\Models\Tenant::class, slugAttribute: 'slug')
            ->tenantProfile(\App\Filament\App\Pages\EditTenantProfile::class)

            // ═══════════════════════════════════════════════════════════════
            // MODO SPA (Single Page Application)
            // Transiciones instantáneas sin recargas de página
            // ═══════════════════════════════════════════════════════════════
            ->spa()

            // ═══════════════════════════════════════════════════════════════
            // LAYOUT ADAPTATIVO (Mobile-First)
            // ═══════════════════════════════════════════════════════════════
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->topNavigation(false)

            // ═══════════════════════════════════════════════════════════════
            // PALETA DE COLORES: INDIGO (Diferenciación del Admin)
            // ═══════════════════════════════════════════════════════════════
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])

            // ═══════════════════════════════════════════════════════════════
            // TIPOGRAFÍA: Inter (Optimizada para móvil)
            // ═══════════════════════════════════════════════════════════════
            ->font('Inter')

            // ═══════════════════════════════════════════════════════════════
            // DARK MODE
            // ═══════════════════════════════════════════════════════════════
            ->darkMode(true)

            // ═══════════════════════════════════════════════════════════════
            // NAVEGACIÓN CON ICONOS SEMÁNTICOS
            // ═══════════════════════════════════════════════════════════════
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Mi Negocio'),
                NavigationGroup::make()
                    ->label('Catálogo'),
                NavigationGroup::make()
                    ->label('Cuenta'),
            ])
            ->navigationItems([
                // Enlace externo: Ver mi página pública
                NavigationItem::make('Ver mi Sitio Público')
                    ->url(fn () => $this->getTenantPublicUrl())
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Mi Negocio')
                    ->sort(99)
                    ->openUrlInNewTab(),

                // Enlace externo: Compartir en WhatsApp
                NavigationItem::make('Compartir Negocio')
                    ->url(fn () => $this->getShareUrl())
                    ->icon('heroicon-o-share')
                    ->group('Mi Negocio')
                    ->sort(100)
                    ->openUrlInNewTab(),
            ])

            // ═══════════════════════════════════════════════════════════════
            // RECURSOS Y PÁGINAS
            // ═══════════════════════════════════════════════════════════════
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\\Filament\\App\\Pages')
            ->pages([
                Dashboard::class,
            ])

            // ═══════════════════════════════════════════════════════════════
            // WIDGETS DEL DASHBOARD (Filament v4 compatible)
            // Ordenados por prioridad: Stats Bento > Límite > Completitud > Acciones
            // ═══════════════════════════════════════════════════════════════
            ->widgets([
                \App\Filament\App\Widgets\TenantStatsWidget::class, // Nuevo: Bento Grid con Growth Hacking
                \App\Filament\App\Widgets\ProductLimitWidget::class,
                \App\Filament\App\Widgets\AccountCompletenessWidget::class,
                \App\Filament\App\Widgets\QuickActionsWidget::class,
                \App\Filament\App\Widgets\ProductLimitWarning::class,
                \App\Filament\App\Widgets\PendingReviewAlert::class,
                \App\Filament\App\Widgets\FreePlanUpsellWidget::class,
                // \App\Filament\App\Widgets\StatsOverview::class, // Reemplazado por TenantStatsWidget
            ])

            // ═══════════════════════════════════════════════════════════════
            // RENDIMIENTO Y UX
            // ═══════════════════════════════════════════════════════════════
            ->maxContentWidth('full')
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')

            // ═══════════════════════════════════════════════════════════════
            // MIDDLEWARE
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

    /**
     * Obtiene la URL pública del tenant actual
     */
    protected function getTenantPublicUrl(): string
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            return url('/'.$tenant->slug);
        }

        return url('/');
    }

    /**
     * Genera URL para compartir en WhatsApp
     */
    protected function getShareUrl(): string
    {
        $tenant = Filament::getTenant();
        if ($tenant) {
            $url = url('/'.$tenant->slug);
            $text = "¡Mira mi negocio {$tenant->name} en Conecta HN!";

            return 'https://wa.me/?text='.urlencode("{$text}\n\n{$url}");
        }

        return '#';
    }
}
