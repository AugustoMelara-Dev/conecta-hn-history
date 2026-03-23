<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
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
            ->login()
            ->authGuard('web')
            ->profile()
            ->brandName('Vito Business OS Admin')
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
                NavigationGroup::make()->label('Configuracion')->collapsed(),
            ])
            ->collapsibleNavigationGroups(false)
            ->maxContentWidth('full')
            ->viteTheme('resources/css/filament/admin-panel.css')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.panel-critical-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.components.local-dev-runtime-reset')->render(),
            )
            ->darkMode(true)
            ->resources([
                \App\Filament\Resources\TenantResource::class,
                \App\Filament\Resources\UserResource::class,
                \App\Filament\Resources\PaymentResource::class,
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
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        Filament::serving(function (): void {
            $panel = Filament::getCurrentPanel();

            if (! $panel || $panel->getId() !== 'admin') {
                return;
            }

            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if (! $user instanceof User) {
                abort(403, 'Acceso denegado. Usuario invalido.');
            }

            $superAdmins = config('auth.super_admins', []);
            $isSuperAdmin = $user->role === UserRole::SUPER_ADMIN;
            $isWhitelistedAdmin = $isSuperAdmin && in_array($user->email, $superAdmins, true);

            if ($isWhitelistedAdmin) {
                return;
            }

            if (! $isSuperAdmin) {
                abort(403, 'Acceso denegado. Se requiere rol de Super Admin.');
            }
        });
    }
}
