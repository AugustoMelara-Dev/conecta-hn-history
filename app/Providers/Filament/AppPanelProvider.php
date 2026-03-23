<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Pages\Dashboard;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            ->brandName('Vito Business OS')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))

            // ═══════════════════════════════════════════════════════════════
            // AUTENTICACIÓN
            // Deshabilitamos registro nativo - usamos wizard público
            // ═══════════════════════════════════════════════════════════════
            ->login()
            // ->registration() // Disabled - using /registrar-negocio wizard
            ->profile()
            ->passwordReset()
            ->emailVerification()

            // ═══════════════════════════════════════════════════════════════
            // MULTI-TENANCY
            // ═══════════════════════════════════════════════════════════════
            ->tenant(\App\Models\Tenant::class, slugAttribute: 'slug')

            // ═══════════════════════════════════════════════════════════════
            // MODO SPA (Single Page Application)
            // Transiciones instantáneas sin recargas de página
            // ═══════════════════════════════════════════════════════════════
            ->viteTheme('resources/css/filament/app-panel.css')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.panel-critical-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.components.local-dev-runtime-reset')->render(),
            )

            // ═══════════════════════════════════════════════════════════════
            // LAYOUT ADAPTATIVO (Mobile-First)
            // ═══════════════════════════════════════════════════════════════
            ->topNavigation(false)

            // ═══════════════════════════════════════════════════════════════
            // PALETA DE COLORES: INDIGO (Diferenciación del Admin)
            // ═══════════════════════════════════════════════════════════════
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])

            // ═══════════════════════════════════════════════════════════════
            // TIPOGRAFÍA: Inter (Optimizada para móvil)
            // ═══════════════════════════════════════════════════════════════
            ->font('Manrope')

            // ═══════════════════════════════════════════════════════════════
            // DARK MODE
            // ═══════════════════════════════════════════════════════════════
            ->darkMode(true)

            // ═══════════════════════════════════════════════════════════════
            // NAVEGACIÓN CON ICONOS SEMÁNTICOS
            // ═══════════════════════════════════════════════════════════════
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Ventas'),
                NavigationGroup::make()
                    ->label('Catalogo'),
                NavigationGroup::make()
                    ->label('Marketing'),
                NavigationGroup::make()
                    ->label('Marketing PRO'),
                NavigationGroup::make()
                    ->label('Inventario'),
                NavigationGroup::make()
                    ->label('Configuracion'),
                NavigationGroup::make()
                    ->label('Cuenta'),
            ])
            ->navigationItems([
                // Enlace externo: Ver mi página pública
                NavigationItem::make('Ver mi sitio publico')
                    ->url(fn () => $this->getTenantPublicUrl())
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Ventas')
                    ->sort(99)
                    ->openUrlInNewTab(),

                // Enlace externo: Compartir en WhatsApp
                NavigationItem::make('Compartir negocio')
                    ->url(fn () => $this->getShareUrl())
                    ->icon('heroicon-o-share')
                    ->group('Ventas')
                    ->sort(100)
                    ->openUrlInNewTab(),
            ])

            // ═══════════════════════════════════════════════════════════════
            // RECURSOS Y PÁGINAS
            // ═══════════════════════════════════════════════════════════════
            ->resources([
                \App\Filament\App\Resources\ProductResource::class,
                \App\Filament\App\Resources\OrderResource::class,
                \App\Filament\App\Resources\Leads\LeadResource::class,
                \App\Filament\App\Resources\FollowerResource::class,
                \App\Filament\App\Resources\CampaignResource::class,
                \App\Filament\App\Resources\InventoryLevelResource::class,
                \App\Filament\App\Resources\InventoryMovementResource::class,
            ])
            ->pages([
                Dashboard::class,
                \App\Filament\App\Pages\Settings::class,
                \App\Filament\App\Pages\ManageMarketing::class,
                \App\Filament\App\Pages\ManageBilling::class,
                \App\Filament\App\Pages\UpgradeToPro::class,
            ])

            // ═══════════════════════════════════════════════════════════════
            // WIDGETS DEL DASHBOARD (Filament v4 compatible)
            // Ordenados por prioridad: Stats Bento > Límite > Completitud > Acciones
            // ═══════════════════════════════════════════════════════════════
            ->widgets([])

            // ═══════════════════════════════════════════════════════════════
            // RENDIMIENTO Y UX
            // ═══════════════════════════════════════════════════════════════
            ->maxContentWidth('full')

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
                \App\Http\Middleware\HydrateTenantContextFromPanelRoute::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->persistentMiddleware([
                \App\Http\Middleware\HydrateTenantContextFromPanelRoute::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        Filament::serving(function (): void {
            if (Filament::getCurrentPanel()?->getId() !== 'app') {
                return;
            }

            $tenant = Filament::getTenant();

            if (! $tenant instanceof Tenant) {
                return;
            }

            app(\App\Domain\Shared\Services\TenantContext::class)
                ->setTenant((string) $tenant->getKey());
        });
    }

    /**
     * Obtiene la URL pública del tenant actual
     */
    protected function getTenantPublicUrl(): string
    {
        $tenant = $this->getCurrentTenant();
        if ($tenant instanceof Tenant) {
            return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
        }

        return url('/');
    }

    /**
     * Genera URL para compartir en WhatsApp
     */
    protected function getShareUrl(): string
    {
        $tenant = $this->getCurrentTenant();
        if ($tenant instanceof Tenant) {
            $url = route('tenant.microsite.show', ['tenant' => $tenant->slug]);
            $text = "¡Mira mi negocio {$tenant->name} en Conecta HN!";

            return 'https://wa.me/?text='.urlencode("{$text}\n\n{$url}");
        }

        return '#';
    }

    private function getCurrentTenant(): ?Tenant
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
