<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Domain\TenantGovernance\Services\TenantOnboardingProgressService;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

final class OnboardingActivationWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.app.widgets.onboarding-activation-widget';

    public function getColumnSpan(): string
    {
        return 'full';
    }

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && $user->canAccessTenant($tenant);
    }

    public function dismissFirstRunWizard(): void
    {
        [$tenant, $user] = $this->authorizedContext();

        app(TenantOnboardingProgressService::class)
            ->dismissFirstRunWizard($tenant, $user, 'owner_deferred');
    }

    /**
     * @return array{
     *   progress:int,
     *   roleLabel:string,
     *   moduleLabel:string,
     *   businessLabel:string,
     *   milestones:list<array{key:string,title:string,copy:string,href:string,completed:bool,status:string,blockedReason:?string}>,
     *   nextAction:array{key:string,title:string,copy:string,href:string},
     *   supportTips:list<array{title:string,copy:string}>,
     *   wizard:array<string,mixed>
     * }
     */
    protected function getViewData(): array
    {
        $moduleContext = $this->resolveModuleContext();
        [$tenant, $user] = $this->authorizedContext();
        $service = app(TenantOnboardingProgressService::class);
        $snapshot = $service->persist($tenant, $user, $moduleContext);
        $wizard = $service->firstRunWizardFor($tenant, $user, $moduleContext);
        $role = (string) ($snapshot['role'] ?? 'owner');

        return [
            'progress' => (int) ($snapshot['progress'] ?? 0),
            'moduleLabel' => $this->moduleLabel($moduleContext),
            'businessLabel' => (string) ($snapshot['businessLabel'] ?? 'Negocio'),
            'roleLabel' => match ($role) {
                'staff' => 'Staff',
                'customer' => 'Usuario final',
                default => 'Dueno',
            },
            'milestones' => (array) ($snapshot['milestones'] ?? []),
            'supportTips' => (array) ($snapshot['supportTips'] ?? []),
            'nextAction' => (array) ($snapshot['nextAction'] ?? [
                'key' => 'none',
                'title' => 'Sin acciones disponibles',
                'copy' => 'No se detecto una siguiente accion.',
                'href' => '',
            ]),
            'wizard' => $wizard,
        ];
    }

    /** @return array{Tenant, User} */
    private function authorizedContext(): array
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        abort_unless(
            $tenant instanceof Tenant
                && $user instanceof User
                && $user->canAccessTenant($tenant),
            403,
        );

        return [$tenant, $user];
    }

    private function resolveModuleContext(): ?string
    {
        $explicit = request()->attributes->get('onboarding_module');
        if (is_string($explicit) && $explicit !== '') {
            return strtolower($explicit);
        }

        $routeName = (string) request()->route()?->getName();
        if ($routeName === '') {
            return null;
        }

        return match (true) {
            str_contains($routeName, '.resources.products.'),
            str_contains($routeName, '.resources.services.'),
            str_contains($routeName, '.resources.menu-sections.') => 'catalog',
            str_contains($routeName, '.resources.appointments.') => 'agenda',
            str_contains($routeName, '.resources.orders.') => 'orders',
            str_contains($routeName, '.resources.campaigns.'),
            str_contains($routeName, '.resources.customer-message-templates.'),
            str_contains($routeName, '.resources.announcements.') => 'marketing',
            str_contains($routeName, '.resources.tenant-users.') => 'team',
            str_contains($routeName, '.pages.settings'),
            str_contains($routeName, '.pages.tenant-profile') => 'settings',
            default => null,
        };
    }

    private function moduleLabel(?string $moduleContext): string
    {
        return match ($moduleContext) {
            'catalog' => 'Catalogo',
            'agenda' => 'Agenda',
            'orders' => 'Ordenes',
            'marketing' => 'Marketing',
            'team' => 'Equipo',
            'settings' => 'Configuracion',
            default => 'General',
        };
    }
}
