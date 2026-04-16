<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantOnboardingState;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Carbon;

final class TenantOnboardingProgressService
{
    /**
     * @return array{
     *   role: string,
     *   progress: int,
     *   milestones: list<array{
     *     key: string,
     *     title: string,
     *     copy: string,
     *     href: string,
     *     completed: bool
     *   }>,
     *   nextAction: array{
     *     key: string,
     *     title: string,
     *     copy: string,
     *     href: string
     *   },
     *   lastCompletedAt: ?Carbon
     * }
     */
    public function buildForTenant(Tenant $tenant, ?User $user = null, ?string $moduleContext = null): array
    {
        $role = $this->normalizeRole($user);
        $tenantSlug = (string) ($tenant->slug ?? '');

        $hasCatalog = $tenant->products()->exists() || $tenant->services()->exists();
        $hasSchedule = $tenant->openingHours()->exists() || filled($tenant->simple_schedule ?? null);
        $hasContactChannel = filled($tenant->whatsapp_number ?? null) || filled($tenant->phone ?? null);
        $hasProfileEssentials = filled($tenant->description ?? null) && filled($tenant->address ?? null);
        $hasMicrositeReady = $tenantSlug !== '' && (bool) ($tenant->is_active ?? false);

        $milestones = match ($role) {
            UserRole::STAFF->value => [
                $this->milestone('staff_profile', 'Perfil operativo', $hasProfileEssentials, 'Completa el perfil del negocio para operar sin dudas.', $this->settingsHref($tenantSlug)),
                $this->milestone('staff_queue', 'Primera cola operativa', $this->tenantHasOrdersOrAppointments($tenant), 'Valida pedidos o citas para dominar el flujo diario.', $this->operationsHref($tenantSlug)),
                $this->milestone('staff_schedule', 'Agenda activa', $hasSchedule, 'Configura horarios reales para evitar fricciÃƒÆ’Ã‚Â³n con clientes.', $this->appointmentsHref($tenantSlug)),
                $this->milestone('staff_contact', 'Canal de contacto', $hasContactChannel, 'Activa WhatsApp/telÃƒÆ’Ã‚Â©fono para seguimiento rÃƒÆ’Ã‚Â¡pido.', $this->settingsHref($tenantSlug)),
                $this->milestone('staff_publish', 'Micrositio listo', $hasMicrositeReady, 'Comprueba que el micrositio estÃƒÆ’Ã‚Â© publicado y confiable.', $this->micrositeHref($tenantSlug)),
            ],
            UserRole::CUSTOMER->value => [
                $this->milestone('customer_account', 'Cuenta creada', true, 'Tu cuenta ya estÃƒÆ’Ã‚Â¡ lista para seguir y reservar.', route('mi-cuenta.edit')),
                $this->milestone('customer_follow', 'Primer negocio seguido', $tenant->followers()->exists(), 'Sigue tus negocios favoritos para recibir novedades.', $this->micrositeHref($tenantSlug)),
                $this->milestone('customer_booking', 'Primera reserva/pedido', $this->tenantHasOrdersOrAppointments($tenant), 'Completa una acciÃƒÆ’Ã‚Â³n para personalizar tu experiencia.', $this->micrositeHref($tenantSlug)),
                $this->milestone('customer_contact', 'Canal confirmado', $hasContactChannel, 'Confirma un canal para recordatorios y estado de pedidos.', route('mi-cuenta.edit')),
                $this->milestone('customer_trust', 'Experiencia completa', $hasProfileEssentials, 'Usa perfiles completos para decidir con confianza.', $this->micrositeHref($tenantSlug)),
            ],
            default => [
                $this->milestone('owner_profile', 'Perfil completo', $hasProfileEssentials, 'Completa descripciÃƒÆ’Ã‚Â³n y direcciÃƒÆ’Ã‚Â³n para transmitir confianza.', $this->settingsHref($tenantSlug)),
                $this->milestone('owner_catalog', 'Primer producto/servicio', $hasCatalog, 'Publica tu primera oferta para no abrir en vacÃƒÆ’Ã‚Â­o.', $this->catalogHref($tenantSlug)),
                $this->milestone('owner_schedule', 'Primer horario', $hasSchedule, 'Define disponibilidad real para evitar fricciÃƒÆ’Ã‚Â³n comercial.', $this->settingsHref($tenantSlug)),
                $this->milestone('owner_contact', 'Canal principal activo', $hasContactChannel, 'Activa WhatsApp/telÃƒÆ’Ã‚Â©fono para conversiones rÃƒÆ’Ã‚Â¡pidas.', $this->settingsHref($tenantSlug)),
                $this->milestone('owner_publish', 'Micrositio publicado', $hasMicrositeReady, 'Verifica que tu pÃƒÆ’Ã‚Â¡gina pÃƒÆ’Ã‚Âºblica estÃƒÆ’Ã‚Â© lista para vender.', $this->micrositeHref($tenantSlug)),
            ],
        };

        $completedCount = collect($milestones)->where('completed', true)->count();
        $progress = (int) round(($completedCount / max(1, count($milestones))) * 100);
        /** @var array{key:string,title:string,copy:string,href:string,completed:bool} $defaultNextAction */
        $defaultNextAction = collect($milestones)->firstWhere('completed', false) ?? $milestones[count($milestones) - 1];
        $nextAction = $this->resolveContextualNextAction(
            $moduleContext,
            $role,
            $milestones,
            $defaultNextAction,
        );
        $lastCompletedAt = $completedCount > 0 ? now() : null;

        return [
            'role' => $role,
            'progress' => $progress,
            'milestones' => $milestones,
            'nextAction' => [
                'key' => (string) $nextAction['key'],
                'title' => (string) $nextAction['title'],
                'copy' => (string) $nextAction['copy'],
                'href' => (string) $nextAction['href'],
            ],
            'lastCompletedAt' => $lastCompletedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function persist(Tenant $tenant, ?User $user = null, ?string $moduleContext = null): array
    {
        $snapshot = $this->buildForTenant($tenant, $user, $moduleContext);

        TenantOnboardingState::updateOrCreate(
            [
                'tenant_id' => (string) $tenant->getKey(),
                'user_id' => $user?->getKey(),
                'role' => $snapshot['role'],
            ],
            [
                'progress' => $snapshot['progress'],
                'next_action_key' => $snapshot['nextAction']['key'],
                'next_action_title' => $snapshot['nextAction']['title'],
                'next_action_copy' => $snapshot['nextAction']['copy'],
                'next_action_href' => $snapshot['nextAction']['href'],
                'milestones' => $snapshot['milestones'],
                'last_completed_at' => $snapshot['lastCompletedAt'],
            ]
        );

        return $snapshot;
    }

    /**
     * @param  list<array{
     *    key: string,
     *    title: string,
     *    copy: string,
     *    href: string,
     *    completed: bool
     *  }>  $milestones
     * @param  array{
     *    key: string,
     *    title: string,
     *    copy: string,
     *    href: string,
     *    completed: bool
     *  }  $defaultNextAction
     * @return array{
     *   key: string,
     *   title: string,
     *   copy: string,
     *   href: string,
     *   completed: bool
     * }
     */
    private function resolveContextualNextAction(
        ?string $moduleContext,
        string $role,
        array $milestones,
        array $defaultNextAction,
    ): array {
        $module = $this->normalizeModuleContext($moduleContext);
        if ($module === null) {
            return $defaultNextAction;
        }

        $preferredKey = $this->preferredMilestoneKeyForModule($role, $module);
        if ($preferredKey === null) {
            return $defaultNextAction;
        }

        /** @var array{key:string,title:string,copy:string,href:string,completed:bool}|null $match */
        $match = collect($milestones)->firstWhere('key', $preferredKey);
        if (! is_array($match)) {
            return $defaultNextAction;
        }

        if ($match['completed'] === true) {
            return $defaultNextAction;
        }

        return $match;
    }

    private function normalizeModuleContext(?string $moduleContext): ?string
    {
        $context = is_string($moduleContext) ? trim(strtolower($moduleContext)) : '';

        return match ($context) {
            'catalog', 'agenda', 'orders', 'marketing', 'team', 'settings' => $context,
            default => null,
        };
    }

    private function preferredMilestoneKeyForModule(string $role, string $moduleContext): ?string
    {
        return match ($role) {
            UserRole::STAFF->value => match ($moduleContext) {
                'catalog' => 'staff_profile',
                'agenda' => 'staff_schedule',
                'orders' => 'staff_queue',
                'marketing' => 'staff_contact',
                'team' => 'staff_profile',
                'settings' => 'staff_profile',
                default => null,
            },
            UserRole::CUSTOMER->value => match ($moduleContext) {
                'catalog' => 'customer_follow',
                'agenda', 'orders' => 'customer_booking',
                'marketing' => 'customer_follow',
                'team' => 'customer_account',
                'settings' => 'customer_contact',
                default => null,
            },
            default => match ($moduleContext) {
                'catalog' => 'owner_catalog',
                'agenda' => 'owner_schedule',
                'orders' => 'owner_publish',
                'marketing' => 'owner_contact',
                'team', 'settings' => 'owner_profile',
                default => null,
            },
        };
    }

    private function normalizeRole(?User $user): string
    {
        $role = $user?->role;
        $raw = UserRole::TENANT_OWNER->value;

        if ($role instanceof BackedEnum) {
            $raw = (string) $role->value;
        }

        return match ($raw) {
            UserRole::SUPER_ADMIN->value => UserRole::TENANT_OWNER->value,
            UserRole::STAFF->value => UserRole::STAFF->value,
            UserRole::CUSTOMER->value => UserRole::CUSTOMER->value,
            default => UserRole::TENANT_OWNER->value,
        };
    }

    /**
     * @return array{key: string, title: string, copy: string, href: string, completed: bool}
     */
    private function milestone(string $key, string $title, bool $completed, string $copy, string $href): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'copy' => $copy,
            'href' => $href,
            'completed' => $completed,
        ];
    }

    private function micrositeHref(string $tenantSlug): string
    {
        return $tenantSlug !== ''
            ? $this->safeRoute('tenant.microsite.show', ['tenant' => $tenantSlug])
            : '#';
    }

    private function settingsHref(string $tenantSlug): string
    {
        return $tenantSlug !== ''
            ? $this->safeRoute('filament.app.pages.settings', ['tenant' => $tenantSlug])
            : '#';
    }

    private function catalogHref(string $tenantSlug): string
    {
        if ($tenantSlug === '') {
            return '#';
        }

        try {
            return $this->safeRoute('filament.app.resources.products.index', ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            return $this->safeRoute('filament.app.pages.dashboard', ['tenant' => $tenantSlug]);
        }
    }

    private function operationsHref(string $tenantSlug): string
    {
        return $tenantSlug !== ''
            ? $this->safeRoute('filament.app.resources.operational-playbook-tasks.index', ['tenant' => $tenantSlug])
            : '#';
    }

    private function appointmentsHref(string $tenantSlug): string
    {
        if ($tenantSlug === '') {
            return '#';
        }

        try {
            return $this->safeRoute('filament.app.resources.appointments.index', ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            return $this->safeRoute('filament.app.pages.dashboard', ['tenant' => $tenantSlug]);
        }
    }

    private function tenantHasOrdersOrAppointments(Tenant $tenant): bool
    {
        return Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->exists() || $tenant->appointments()->exists();
    }

    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return '#';
        }
    }
}
