<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface;
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
     *   businessType: string,
     *   businessLabel: string,
     *   progress: int,
     *   milestones: list<array{key:string,title:string,copy:string,href:string,completed:bool}>,
     *   nextAction: array{key:string,title:string,copy:string,href:string},
     *   supportTips: list<array{title:string,copy:string}>,
     *   lastCompletedAt: ?Carbon
     * }
     */
    public function buildForTenant(Tenant $tenant, ?User $user = null, ?string $moduleContext = null): array
    {
        $role = $this->normalizeRole($user);
        $tenantSlug = (string) ($tenant->slug ?? '');
        $businessType = $this->businessType($tenant);
        $manifestOnboarding = app(RubroManifestReaderInterface::class)->onboardingForTenant($tenant);

        $hasCatalog = $tenant->products()->exists() || $tenant->services()->exists();
        $hasSchedule = $tenant->openingHours()->exists() || filled($tenant->simple_schedule ?? null);
        $hasContactChannel = filled($tenant->whatsapp_number ?? null) || filled($tenant->phone ?? null);
        $hasProfileEssentials = filled($tenant->description ?? null) && filled($tenant->address ?? null);
        $hasMicrositeReady = $tenantSlug !== '' && (bool) ($tenant->is_active ?? false);
        $hasOperations = $this->tenantHasOrdersOrAppointments($tenant);

        $milestones = match ($role) {
            UserRole::STAFF->value => $this->staffMilestones($tenantSlug, $hasProfileEssentials, $hasSchedule, $hasContactChannel, $hasMicrositeReady, $hasOperations),
            UserRole::CUSTOMER->value => $this->customerMilestones($tenantSlug, $tenant, $hasProfileEssentials, $hasContactChannel, $hasOperations),
            default => $this->ownerMilestones($tenantSlug, $businessType, $hasProfileEssentials, $hasCatalog, $hasSchedule, $hasContactChannel, $hasMicrositeReady, $manifestOnboarding),
        };

        $completedCount = collect($milestones)->where('completed', true)->count();
        $progress = (int) round(($completedCount / max(1, count($milestones))) * 100);
        /** @var array{key:string,title:string,copy:string,href:string,completed:bool} $defaultNextAction */
        $defaultNextAction = collect($milestones)->firstWhere('completed', false) ?? $milestones[count($milestones) - 1];
        $nextAction = $this->resolveContextualNextAction($moduleContext, $role, $milestones, $defaultNextAction);
        $lastCompletedAt = $completedCount > 0 ? now() : null;

        return [
            'role' => $role,
            'businessType' => $businessType,
            'businessLabel' => $this->businessLabel($tenant, $businessType),
            'progress' => $progress,
            'milestones' => $milestones,
            'nextAction' => [
                'key' => (string) $nextAction['key'],
                'title' => (string) $nextAction['title'],
                'copy' => (string) $nextAction['copy'],
                'href' => (string) $nextAction['href'],
            ],
            'supportTips' => $this->supportTips($businessType, $progress, $manifestOnboarding),
            'lastCompletedAt' => $lastCompletedAt,
        ];
    }

    /** @return array<string, mixed> */
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
     * @return list<array{key:string,title:string,copy:string,href:string,completed:bool}>
     */
    private function ownerMilestones(
        string $tenantSlug,
        string $businessType,
        bool $hasProfile,
        bool $hasCatalog,
        bool $hasSchedule,
        bool $hasContact,
        bool $isPublished,
        array $manifestOnboarding = [],
    ): array
    {
        $catalogTitle = match ($businessType) {
            'food' => 'Primer plato o combo',
            'service' => 'Primer servicio reservable',
            'hybrid' => 'Primera oferta vendible',
            default => 'Primer producto publicado',
        };

        $catalogCopy = match ($businessType) {
            'food' => 'Carga al menos un plato con precio para que el cliente pueda decidir rapido.',
            'service' => 'Define un servicio con duracion, precio y disponibilidad para recibir solicitudes claras.',
            'hybrid' => 'Publica un producto o servicio base antes de invitar clientes al perfil.',
            default => 'Publica tu primera oferta para que el directorio no mande clientes a una vitrina vacia.',
        };

        $scheduleCopy = match ($businessType) {
            'food' => 'Configura horarios reales de atencion, cocina o delivery para evitar reclamos.',
            'service' => 'Configura disponibilidad para que las reservas no choquen con tu operacion.',
            default => 'Define disponibilidad real para evitar friccion comercial.',
        };

        $manifestMilestones = $this->ownerMilestonesFromManifest(
            tenantSlug: $tenantSlug,
            businessType: $businessType,
            hasProfile: $hasProfile,
            hasCatalog: $hasCatalog,
            hasSchedule: $hasSchedule,
            hasContact: $hasContact,
            isPublished: $isPublished,
            manifestOnboarding: $manifestOnboarding,
        );

        if ($manifestMilestones !== []) {
            $legacyMilestones = [
                $this->milestone('owner_profile', 'Perfil confiable', $hasProfile, 'Agrega descripcion, direccion y datos base para que el cliente entienda que vendes y donde atiendes.', $this->settingsHref($tenantSlug)),
                $this->milestone('owner_catalog', $catalogTitle, $hasCatalog, $catalogCopy, $this->catalogHref($tenantSlug, $businessType)),
                $this->milestone('owner_schedule', 'Horario o disponibilidad', $hasSchedule, $scheduleCopy, $this->settingsHref($tenantSlug)),
                $this->milestone('owner_contact', 'Canal principal activo', $hasContact, 'Activa WhatsApp o telefono para que el cliente sepa como contactarte sin escribir a soporte.', $this->settingsHref($tenantSlug)),
                $this->milestone('owner_publish', 'Micrositio publicado', $isPublished, 'Abre tu pagina publica y revisa que nombre, contacto y oferta se vean correctos.', $this->micrositeHref($tenantSlug)),
            ];

            return collect($manifestMilestones)
                ->concat($legacyMilestones)
                ->unique(fn (array $milestone): string => (string) $milestone['title'])
                ->take(5)
                ->values()
                ->all();
        }

        return [
            $this->milestone('owner_profile', 'Perfil confiable', $hasProfile, 'Agrega descripcion, direccion y datos base para que el cliente entienda que vendes y donde atiendes.', $this->settingsHref($tenantSlug)),
            $this->milestone('owner_catalog', $catalogTitle, $hasCatalog, $catalogCopy, $this->catalogHref($tenantSlug, $businessType)),
            $this->milestone('owner_schedule', 'Horario o disponibilidad', $hasSchedule, $scheduleCopy, $this->settingsHref($tenantSlug)),
            $this->milestone('owner_contact', 'Canal principal activo', $hasContact, 'Activa WhatsApp o telefono para que el cliente sepa como contactarte sin escribir a soporte.', $this->settingsHref($tenantSlug)),
            $this->milestone('owner_publish', 'Micrositio publicado', $isPublished, 'Abre tu pagina publica y revisa que nombre, contacto y oferta se vean correctos.', $this->micrositeHref($tenantSlug)),
        ];
    }

    /**
     * @return list<array{key:string,title:string,copy:string,href:string,completed:bool}>
     */
    private function ownerMilestonesFromManifest(
        string $tenantSlug,
        string $businessType,
        bool $hasProfile,
        bool $hasCatalog,
        bool $hasSchedule,
        bool $hasContact,
        bool $isPublished,
        array $manifestOnboarding,
    ): array {
        $healthChecks = collect((array) ($manifestOnboarding['health_checks'] ?? []))
            ->values();

        if ($healthChecks->isEmpty()) {
            return [];
        }

        return $healthChecks
            ->map(function (mixed $check, int $index) use ($tenantSlug, $businessType, $hasProfile, $hasCatalog, $hasSchedule, $hasContact, $isPublished): array {
                $state = is_array($check) ? (string) ($check['state'] ?? '') : (string) $check;
                $title = is_array($check) ? (string) ($check['title'] ?? '') : '';
                $pendingCopy = is_array($check) ? (string) ($check['pending_copy'] ?? '') : '';
                $completeCopy = is_array($check) ? (string) ($check['complete_copy'] ?? '') : '';
                $completed = $this->manifestStateCompleted($state, $hasProfile, $hasCatalog, $hasSchedule, $hasContact, $isPublished);

                return $this->milestone(
                    "owner_manifest_{$index}",
                    $title !== '' ? $title : $this->manifestStateTitle($state),
                    $completed,
                    $completed
                        ? ($completeCopy !== '' ? $completeCopy : 'Este frente ya esta cubierto.')
                        : ($pendingCopy !== '' ? $pendingCopy : 'Completa este frente para destrabar el siguiente paso del negocio.'),
                    $this->manifestStateHref($tenantSlug, $businessType, $state),
                );
            })
            ->all();
    }

    /** @return list<array{key:string,title:string,copy:string,href:string,completed:bool}> */
    private function staffMilestones(string $tenantSlug, bool $hasProfile, bool $hasSchedule, bool $hasContact, bool $isPublished, bool $hasOperations): array
    {
        return [
            $this->milestone('staff_profile', 'Entender el negocio', $hasProfile, 'Revisa el perfil para responder igual que el dueno y evitar mensajes contradictorios.', $this->settingsHref($tenantSlug)),
            $this->milestone('staff_queue', 'Primera cola operativa', $hasOperations, 'Valida pedidos, citas o tareas abiertas para dominar el flujo diario.', $this->operationsHref($tenantSlug)),
            $this->milestone('staff_schedule', 'Agenda activa', $hasSchedule, 'Confirma horarios reales antes de prometer tiempos al cliente.', $this->appointmentsHref($tenantSlug)),
            $this->milestone('staff_contact', 'Canal de contacto', $hasContact, 'Verifica WhatsApp o telefono para seguimiento rapido.', $this->settingsHref($tenantSlug)),
            $this->milestone('staff_publish', 'Micrositio listo', $isPublished, 'Comprueba que la pagina publica este lista para compartirse.', $this->micrositeHref($tenantSlug)),
        ];
    }

    /** @return list<array{key:string,title:string,copy:string,href:string,completed:bool}> */
    private function customerMilestones(string $tenantSlug, Tenant $tenant, bool $hasProfile, bool $hasContact, bool $hasOperations): array
    {
        return [
            $this->milestone('customer_account', 'Cuenta creada', true, 'Tu cuenta ya esta lista para seguir, reservar y comprar en negocios locales.', route('mi-cuenta.edit')),
            $this->milestone('customer_follow', 'Primer negocio seguido', $tenant->followers()->exists(), 'Sigue tus negocios favoritos para recibir novedades y encontrarlos rapido.', $this->micrositeHref($tenantSlug)),
            $this->milestone('customer_booking', 'Primera reserva o pedido', $hasOperations, 'Completa una accion para que la plataforma aprenda tus preferencias.', $this->micrositeHref($tenantSlug)),
            $this->milestone('customer_contact', 'Canal confirmado', $hasContact, 'Confirma un canal para recordatorios y estado de pedidos.', route('mi-cuenta.edit')),
            $this->milestone('customer_trust', 'Experiencia completa', $hasProfile, 'Usa perfiles completos para decidir con confianza.', $this->micrositeHref($tenantSlug)),
        ];
    }

    /**
     * @param list<array{key:string,title:string,copy:string,href:string,completed:bool}> $milestones
     * @param array{key:string,title:string,copy:string,href:string,completed:bool} $defaultNextAction
     * @return array{key:string,title:string,copy:string,href:string,completed:bool}
     */
    private function resolveContextualNextAction(?string $moduleContext, string $role, array $milestones, array $defaultNextAction): array
    {
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
        if (! is_array($match) || $match['completed'] === true) {
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
                'agenda' => 'staff_schedule',
                'orders' => 'staff_queue',
                'marketing' => 'staff_contact',
                default => 'staff_profile',
            },
            UserRole::CUSTOMER->value => match ($moduleContext) {
                'agenda', 'orders' => 'customer_booking',
                'settings' => 'customer_contact',
                default => 'customer_follow',
            },
            default => match ($moduleContext) {
                'catalog' => 'owner_catalog',
                'agenda' => 'owner_schedule',
                'orders' => 'owner_publish',
                'marketing' => 'owner_contact',
                default => 'owner_profile',
            },
        };
    }

    private function normalizeRole(?User $user): string
    {
        $role = $user?->role;
        $raw = UserRole::TENANT_OWNER->value;

        if ($role instanceof BackedEnum) {
            $raw = (string) $role->value;
        } elseif (is_string($role)) {
            $raw = $role;
        }

        return match ($raw) {
            UserRole::SUPER_ADMIN->value => UserRole::TENANT_OWNER->value,
            UserRole::STAFF->value => UserRole::STAFF->value,
            UserRole::CUSTOMER->value => UserRole::CUSTOMER->value,
            default => UserRole::TENANT_OWNER->value,
        };
    }

    private function businessType(Tenant $tenant): string
    {
        $raw = $tenant->business_type;
        if ($raw instanceof BackedEnum) {
            return (string) $raw->value;
        }

        return in_array($raw, ['retail', 'food', 'service', 'hybrid'], true) ? (string) $raw : 'retail';
    }

    private function businessLabel(Tenant $tenant, string $businessType): string
    {
        $label = (string) data_get(app(RubroManifestReaderInterface::class)->forTenant($tenant), 'presentation.label', '');

        if ($label !== '') {
            return $label;
        }

        return match ($businessType) {
            'food' => 'Restaurante y comida',
            'service' => 'Servicios y citas',
            'hybrid' => 'Negocio mixto',
            default => 'Tienda y catalogo',
        };
    }

    /** @return list<array{title:string,copy:string}> */
    private function supportTips(string $businessType, int $progress, array $manifestOnboarding = []): array
    {
        $primary = $progress >= 100
            ? ['title' => 'Listo para compartir', 'copy' => 'Tu base esta completa. Ahora comparte el micrositio y empieza a medir contactos, pedidos o reservas.']
            : ['title' => 'Siguiente paso claro', 'copy' => 'Completa el boton principal antes de pedir soporte: el panel actualiza este checklist automaticamente.'];

        $manifestCopy = (string) ($manifestOnboarding['copy'] ?? '');

        if ($manifestCopy !== '') {
            return [$primary, [
                'title' => (string) ($manifestOnboarding['title'] ?? 'Ruta del rubro'),
                'copy' => $manifestCopy,
            ]];
        }

        $specific = match ($businessType) {
            'food' => ['title' => 'Para comida', 'copy' => 'Prioriza menu, horarios y WhatsApp. Es lo que mas evita reclamos de clientes hambrientos.'],
            'service' => ['title' => 'Para servicios', 'copy' => 'Prioriza servicios, disponibilidad y confianza del perfil antes de abrir agenda.'],
            'hybrid' => ['title' => 'Para negocio mixto', 'copy' => 'Empieza con una oferta principal y luego activa productos, servicios o agenda segun demanda.'],
            default => ['title' => 'Para tienda', 'copy' => 'Prioriza catalogo, precios y contacto. Sin oferta visible el directorio no convierte.'],
        };

        return [$primary, $specific];
    }

    private function manifestStateCompleted(
        string $state,
        bool $hasProfile,
        bool $hasCatalog,
        bool $hasSchedule,
        bool $hasContact,
        bool $isPublished,
    ): bool {
        return match ($state) {
            'has_profile', 'profile_branding', 'profile_branding_whatsapp' => $hasProfile,
            'has_products', 'has_menu_items', 'has_products_or_services', 'products', 'services' => $hasCatalog,
            'has_provider', 'schedule', 'booking_ready', 'staff' => $hasSchedule,
            'has_payment_method', 'has_order_channel', 'service_channel', 'whatsapp' => $hasContact,
            'coverage', 'pickup_delivery', 'hybrid_coverage' => $isPublished,
            default => false,
        };
    }

    private function manifestStateTitle(string $state): string
    {
        return $state !== ''
            ? str_replace('  ', ' ', ucwords(str_replace('_', ' ', $state)))
            : 'Hito del rubro';
    }

    private function manifestStateHref(string $tenantSlug, string $businessType, string $state): string
    {
        return match ($state) {
            'has_products', 'has_menu_items', 'has_products_or_services', 'products', 'services' => $this->catalogHref($tenantSlug, $businessType),
            'has_provider', 'schedule', 'booking_ready', 'staff' => $this->appointmentsHref($tenantSlug),
            'coverage', 'pickup_delivery', 'hybrid_coverage' => $this->micrositeHref($tenantSlug),
            default => $this->settingsHref($tenantSlug),
        };
    }

    /** @return array{key:string,title:string,copy:string,href:string,completed:bool} */
    private function milestone(string $key, string $title, bool $completed, string $copy, string $href): array
    {
        return compact('key', 'title', 'copy', 'href', 'completed');
    }

    private function micrositeHref(string $tenantSlug): string
    {
        return $tenantSlug !== '' ? $this->safeRoute('tenant.microsite.show', ['tenant' => $tenantSlug]) : '#';
    }

    private function settingsHref(string $tenantSlug): string
    {
        return $tenantSlug !== '' ? $this->safeRoute('filament.app.pages.settings', ['tenant' => $tenantSlug]) : '#';
    }

    private function catalogHref(string $tenantSlug, string $businessType = 'retail'): string
    {
        if ($tenantSlug === '') {
            return '#';
        }

        $resource = $businessType === 'service'
            ? 'filament.app.resources.services.index'
            : 'filament.app.resources.products.index';

        try {
            return $this->safeRoute($resource, ['tenant' => $tenantSlug]);
        } catch (\Throwable) {
            return $this->safeRoute('filament.app.pages.dashboard', ['tenant' => $tenantSlug]);
        }
    }

    private function operationsHref(string $tenantSlug): string
    {
        return $tenantSlug !== '' ? $this->safeRoute('filament.app.resources.operational-playbook-tasks.index', ['tenant' => $tenantSlug]) : '#';
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

    /** @param array<string, mixed> $parameters */
    private function safeRoute(string $name, array $parameters = []): string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return '#';
        }
    }
}
