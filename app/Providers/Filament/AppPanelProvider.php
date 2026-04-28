<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Pages\CustomerInbox;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Pages\BusinessCopilot;
use App\Filament\App\Pages\GrowthCenter;
use App\Models\Tenant;
use Filament\Facades\Filament;
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
 * Panel operativo para negocios.
 *
 * Priorizamos navegacion clara, tiempos cortos y una experiencia solida
 * en movil para duenos de negocio que gestionan su operacion desde el telefono.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->login()
            // ->registration() // El alta se maneja en el flujo publico.
            ->profile()
            ->passwordReset()
            ->emailVerification()
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantProfile(\App\Filament\App\Pages\EditTenantProfile::class)
            ->topNavigation(false)
            ->sidebarWidth('18rem')
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)
            ->collapsibleNavigationGroups(false)
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])
            ->font('Manrope')
            ->darkMode(true)
            ->navigationGroups([
                NavigationGroup::make()->label('Ventas'),
                NavigationGroup::make()->label('Catalogo'),
                NavigationGroup::make()->label('Servicios'),
                NavigationGroup::make()->label('Marketing'),
                NavigationGroup::make()->label('Marketing PRO'),
                NavigationGroup::make()->label('Operacion'),
                NavigationGroup::make()->label('Inventario'),
                NavigationGroup::make()->label('Configuracion'),
                NavigationGroup::make()->label('Cuenta'),
            ])
            ->navigationItems([
                NavigationItem::make('Ver mi sitio publico')
                    ->url(fn () => $this->getTenantPublicUrl())
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Ventas')
                    ->sort(99)
                    ->openUrlInNewTab(),

                NavigationItem::make('Compartir negocio')
                    ->url(fn () => $this->getShareUrl())
                    ->icon('heroicon-o-share')
                    ->group('Ventas')
                    ->sort(100)
                    ->openUrlInNewTab(),
            ])
            ->resources([
                \App\Filament\App\Resources\OperationalPlaybookTaskResource::class,
                \App\Filament\App\Resources\ProductResource::class,
                \App\Filament\App\Resources\MenuSectionResource::class,
                \App\Filament\App\Resources\OrderResource::class,
                \App\Filament\App\Resources\ReviewResource::class,
                \App\Filament\App\Resources\CustomerProfileResource::class,
                \App\Filament\App\Resources\CustomerMessageTemplateResource::class,
                \App\Filament\App\Resources\CustomerTouchpointResource::class,
                \App\Filament\App\Resources\ServiceResource::class,
                \App\Filament\App\Resources\AppointmentResource::class,
                \App\Filament\App\Resources\ServiceProviderResource::class,
                \App\Filament\App\Resources\Leads\LeadResource::class,
                \App\Filament\App\Resources\FollowerResource::class,
                \App\Filament\App\Resources\CampaignResource::class,
                \App\Filament\App\Resources\CouponResource::class,
                \App\Filament\App\Resources\AnnouncementResource::class,
                \App\Filament\App\Resources\LoyaltyProgramResource::class,
                \App\Filament\App\Resources\LoyaltyRewardResource::class,
                \App\Filament\App\Resources\InventoryLevelResource::class,
                \App\Filament\App\Resources\InventoryMovementResource::class,
                \App\Filament\App\Resources\LocationResource::class,
                \App\Filament\App\Resources\TenantUserResource::class,
                \App\Filament\App\Resources\Dynamic\EntityDefinitionResource::class,
                \App\Filament\App\Resources\Dynamic\DynamicPrintTemplateResource::class,
                \App\Filament\App\Resources\Dynamic\DynamicRecordResource::class,
            ])
            ->pages([
                Dashboard::class,
                BusinessCopilot::class,
                CustomerInbox::class,
                GrowthCenter::class,
                \App\Filament\App\Pages\Settings::class,
                \App\Filament\App\Pages\ManageMarketing::class,
                \App\Filament\App\Pages\ManageBilling::class,
                \App\Filament\App\Pages\UpgradeToPro::class,
            ])
            ->widgets([])
            ->maxContentWidth('full')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.components.app-inline-overrides'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                \App\Http\Middleware\NormalizeInternalRedirectLocation::class,
                \App\Http\Middleware\SecurityHeaders::class,
                \App\Http\Middleware\HydrateTenantContextFromPanelRoute::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->persistentMiddleware([
                \App\Http\Middleware\HydrateTenantContextFromPanelRoute::class,
            ])
            ->authMiddleware([
                \App\Http\Middleware\SameOriginFilamentAuthenticate::class,
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

    protected function getTenantPublicUrl(): string
    {
        $tenant = $this->getCurrentTenant();

        if ($tenant instanceof Tenant) {
            return route('tenant.microsite.show', ['tenant' => $tenant->slug]);
        }

        return url('/');
    }

    protected function getShareUrl(): string
    {
        $tenant = $this->getCurrentTenant();

        if ($tenant instanceof Tenant) {
            $url = route('tenant.microsite.show', ['tenant' => $tenant->slug]);
            $text = "Mira mi negocio {$tenant->name} en Conecta HN.";

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
