<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\AdminLogin::class)
            ->authGuard('web')
            ->profile()
            ->brandLogo(fn () => view('filament.components.brand'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Zinc,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Manrope')
            ->navigationGroups([
                NavigationGroup::make()->label('Dashboard'),
                NavigationGroup::make()->label('Gerencia'),
                NavigationGroup::make()->label('Negocios'),
                NavigationGroup::make()->label('Finanzas'),
                NavigationGroup::make()->label('Seguridad'),
                NavigationGroup::make()->label('Configuración')->collapsed(),
            ])
            ->sidebarWidth('18rem')
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarFullyCollapsibleOnDesktop(false)
            ->collapsibleNavigationGroups(false)
            ->maxContentWidth('full')
            ->darkMode(true)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): \Illuminate\Contracts\View\View => view('filament.components.admin-inline-overrides'),
            )
            ->resources([
                \App\Filament\Resources\ActivityLogResource::class,
                \App\Filament\Resources\OperationalPlaybookTaskResource::class,
                \App\Filament\Resources\TenantResource::class,
                \App\Filament\Resources\UserResource::class,
                \App\Filament\Resources\PaymentResource::class,
                \App\Filament\Resources\OrderResource::class,
                \App\Filament\Resources\ProductResource::class,
                \App\Filament\Resources\CouponResource::class,
                \App\Filament\Resources\CategoryResource::class,
                \App\Filament\Resources\CityResource::class,
            ])
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Pages\ManageGeneralSettings::class,
                \App\Filament\Pages\Billing::class,
                \App\Filament\Pages\ArchitectureHealth::class,
            ])
            ->widgets([])
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
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
