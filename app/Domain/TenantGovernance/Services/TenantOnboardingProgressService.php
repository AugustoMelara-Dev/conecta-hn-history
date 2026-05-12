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
        $commercialMode = $this->commercialMode($tenant);
        $manifestOnboarding = $this->manifestOnboardingForTenant($tenant);

        $hasCatalog = $tenant->products()->withoutGlobalScopes()->exists()
            || $tenant->services()->withoutGlobalScopes()->exists();
        $hasSchedule = $tenant->openingHours()->withoutGlobalScopes()->exists()
            || filled($tenant->simple_schedule ?? null);
        $hasContactChannel = filled($tenant->whatsapp_number ?? null) || filled($tenant->phone ?? null);
        $hasProfileEssentials = filled($tenant->description ?? null)
            && (
                filled($tenant->whatsapp_number ?? null)
                || filled($tenant->phone ?? null)
                || filled($tenant->city_id ?? null)
                || filled($tenant->category_id ?? null)
            );
        $hasMicrositeReady = $tenantSlug !== '' && (bool) ($tenant->is_active ?? false);
        $hasOperations = $this->tenantHasOrdersOrAppointments($tenant);

        $milestones = match ($role) {
            UserRole::STAFF->value => $this->staffMilestones($tenantSlug, $hasProfileEssentials, $hasSchedule, $hasContactChannel, $hasMicrositeReady, $hasOperations),
            UserRole::CUSTOMER->value => $this->customerMilestones($tenantSlug, $tenant, $hasProfileEssentials, $hasContactChannel, $hasOperations),
            default => $this->ownerMilestones($tenantSlug, $businessType, $commercialMode, $hasProfileEssentials, $hasCatalog, $hasSchedule, $hasContactChannel, $hasMicrositeReady, $manifestOnboarding),
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
            'businessLabel' => $this->businessLabel($tenant, $businessType, $commercialMode),
            'progress' => $progress,
            'milestones' => $milestones,
            'nextAction' => [
                'key' => (string) $nextAction['key'],
                'title' => (string) $nextAction['title'],
                'copy' => (string) $nextAction['copy'],
                'href' => (string) $nextAction['href'],
            ],
            'supportTips' => $this->supportTips($businessType, $commercialMode, $progress, $manifestOnboarding),
            'lastCompletedAt' => $lastCompletedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function persist(Tenant $tenant, ?User $user = null, ?string $moduleContext = null): array
    {
        $snapshot = $this->buildForTenant($tenant, $user, $moduleContext);
        $existingState = TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('user_id', $user?->getKey())
            ->where('role', $snapshot['role'])
            ->first();
        $snapshot['lastCompletedAt'] = $this->resolveLastCompletedAt($snapshot, $existingState);
        $this->recordMilestoneEvents($tenant, $user, $snapshot, $existingState);

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
     * @return array{
     *   progress:int,
     *   dismissed:bool,
     *   completed:bool,
     *   currentStepKey:?string,
     *   completedSteps:list<string>,
     *   steps:list<array{key:string,title:string,copy:string,href:string,completed:bool,current:bool}>,
     *   startedAt:?Carbon,
     *   completedAt:?Carbon,
     *   dismissedAt:?Carbon,
     *   abandonedAt:?Carbon
     * }
     */
    public function firstRunWizardFor(Tenant $tenant, ?User $user = null, ?string $moduleContext = null): array
    {
        $snapshot = $this->persist($tenant, $user, $moduleContext);
        $state = $this->stateForSnapshot($tenant, $user, (string) $snapshot['role']);

        if (! $this->hasFirstRunWizardColumns() || ! $state instanceof TenantOnboardingState) {
            return $this->emptyFirstRunWizard($snapshot);
        }

        $this->markFirstRunStarted($state);
        $this->markFirstRunAbandonedIfStale($state);
        $this->markFirstRunCompletedIfAllStepsDone($state, $snapshot);

        $state->forceFill(['first_run_last_seen_at' => now()])->save();
        $state = $state->fresh();

        if (! $state instanceof TenantOnboardingState) {
            return $this->emptyFirstRunWizard($snapshot);
        }

        return $this->buildFirstRunWizard($snapshot, $state);
    }

    public function completeFirstRunStep(Tenant $tenant, ?User $user, string $stepKey): array
    {
        $snapshot = $this->persist($tenant, $user);
        $state = $this->stateForSnapshot($tenant, $user, (string) $snapshot['role']);

        if (! $this->hasFirstRunWizardColumns() || ! $state instanceof TenantOnboardingState) {
            return $this->emptyFirstRunWizard($snapshot);
        }

        $validSteps = $this->firstRunStepKeys((array) ($snapshot['milestones'] ?? []));
        if (! in_array($stepKey, $validSteps, true)) {
            return $this->buildFirstRunWizard($snapshot, $state);
        }

        $completedSteps = $this->normalizedCompletedFirstRunSteps($state, $snapshot);
        if (! in_array($stepKey, $completedSteps, true)) {
            $completedSteps[] = $stepKey;
            $this->recordFirstRunEvent($state, $stepKey, 'first_run_step_completed');
        }

        $nextStepKey = collect($validSteps)
            ->first(fn (string $candidate): bool => ! in_array($candidate, $completedSteps, true));

        $updates = [
            'first_run_completed_steps' => array_values($completedSteps),
            'first_run_step_key' => $nextStepKey ?: $stepKey,
            'first_run_last_seen_at' => now(),
        ];

        if ($nextStepKey === null && ! $state->first_run_completed_at instanceof Carbon) {
            $updates['first_run_completed_at'] = now();
            $this->recordFirstRunEvent($state, $stepKey, 'first_run_completed');
        }

        $state->forceFill($updates)->save();

        return $this->buildFirstRunWizard($snapshot, $state->fresh() ?? $state);
    }

    public function moveFirstRunStep(Tenant $tenant, ?User $user, string $stepKey): array
    {
        $snapshot = $this->persist($tenant, $user);
        $state = $this->stateForSnapshot($tenant, $user, (string) $snapshot['role']);

        if (! $this->hasFirstRunWizardColumns() || ! $state instanceof TenantOnboardingState) {
            return $this->emptyFirstRunWizard($snapshot);
        }

        if (! in_array($stepKey, $this->firstRunStepKeys((array) ($snapshot['milestones'] ?? [])), true)) {
            return $this->buildFirstRunWizard($snapshot, $state);
        }

        $state->forceFill([
            'first_run_step_key' => $stepKey,
            'first_run_last_seen_at' => now(),
        ])->save();

        $this->recordFirstRunEvent($state, $stepKey, 'first_run_step_viewed');

        return $this->buildFirstRunWizard($snapshot, $state->fresh() ?? $state);
    }

    public function dismissFirstRunWizard(Tenant $tenant, ?User $user, string $reason = 'user_dismissed'): array
    {
        $snapshot = $this->persist($tenant, $user);
        $state = $this->stateForSnapshot($tenant, $user, (string) $snapshot['role']);

        if (! $this->hasFirstRunWizardColumns() || ! $state instanceof TenantOnboardingState) {
            return $this->emptyFirstRunWizard($snapshot);
        }

        if (! $state->first_run_dismissed_at instanceof Carbon) {
            $this->recordFirstRunEvent($state, (string) ($state->first_run_step_key ?? 'first_run'), 'first_run_dismissed');
        }

        $state->forceFill([
            'first_run_dismissed_at' => $state->first_run_dismissed_at ?? now(),
            'first_run_skipped_reason' => str($reason)->limit(120, '')->value(),
            'first_run_last_seen_at' => now(),
        ])->save();

        return $this->buildFirstRunWizard($snapshot, $state->fresh() ?? $state);
    }

    /** @param array<string, mixed> $snapshot */
    private function recordMilestoneEvents(Tenant $tenant, ?User $user, array $snapshot, ?TenantOnboardingState $existingState): void
    {
        if (! $this->schema()->hasTable('tenant_onboarding_milestone_events')) {
            return;
        }

        $currentCompletedKeys = $this->completedMilestoneKeys((array) ($snapshot['milestones'] ?? []));
        $previousCompletedKeys = $this->completedMilestoneKeys((array) ($existingState?->milestones ?? []));
        $newlyCompletedKeys = collect($currentCompletedKeys)->diff($previousCompletedKeys)->values();
        $regressedKeys = collect($previousCompletedKeys)->diff($currentCompletedKeys)->values();

        if ($newlyCompletedKeys->isEmpty() && $regressedKeys->isEmpty()) {
            return;
        }

        $now = now();
        $base = [
            'tenant_id' => (string) $tenant->getKey(),
            'user_id' => $user?->getKey(),
            'role' => (string) $snapshot['role'],
            'snapshot_progress' => (int) $snapshot['progress'],
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $events = $newlyCompletedKeys
            ->map(fn (string $key): array => $base + [
                'milestone_key' => $key,
                'event_type' => 'completed',
                'metadata' => json_encode(['source' => 'snapshot_persist'], JSON_THROW_ON_ERROR),
            ])
            ->concat($regressedKeys->map(fn (string $key): array => $base + [
                'milestone_key' => $key,
                'event_type' => 'regressed',
                'metadata' => json_encode(['source' => 'snapshot_persist'], JSON_THROW_ON_ERROR),
            ]))
            ->values()
            ->all();

        $this->db()->table('tenant_onboarding_milestone_events')->insert($events);
    }

    private function markFirstRunStarted(TenantOnboardingState $state): void
    {
        if ($state->first_run_started_at instanceof Carbon) {
            return;
        }

        $state->forceFill([
            'first_run_started_at' => now(),
            'first_run_step_key' => $state->first_run_step_key ?: $this->firstIncompleteMilestoneKey((array) ($state->milestones ?? [])),
        ])->save();

        $this->recordFirstRunEvent($state, (string) ($state->first_run_step_key ?? 'first_run'), 'first_run_started');
    }

    private function markFirstRunAbandonedIfStale(TenantOnboardingState $state): void
    {
        if ($state->first_run_abandoned_at instanceof Carbon
            || $state->first_run_completed_at instanceof Carbon
            || $state->first_run_dismissed_at instanceof Carbon
            || ! $state->first_run_last_seen_at instanceof Carbon
            || $state->first_run_last_seen_at->greaterThanOrEqualTo(now()->subDay())) {
            return;
        }

        $state->forceFill(['first_run_abandoned_at' => now()])->save();
        $this->recordFirstRunEvent($state, (string) ($state->first_run_step_key ?? 'first_run'), 'first_run_abandoned');
    }

    /** @param array<string, mixed> $snapshot */
    private function markFirstRunCompletedIfAllStepsDone(TenantOnboardingState $state, array $snapshot): void
    {
        if ($state->first_run_completed_at instanceof Carbon) {
            return;
        }

        $validSteps = $this->firstRunStepKeys((array) ($snapshot['milestones'] ?? []));
        if ($validSteps === []) {
            return;
        }

        $completedSteps = $this->normalizedCompletedFirstRunSteps($state, $snapshot);
        $hasIncompleteStep = collect($validSteps)
            ->contains(fn (string $stepKey): bool => ! in_array($stepKey, $completedSteps, true));

        if ($hasIncompleteStep) {
            return;
        }

        $state->forceFill([
            'first_run_completed_steps' => $completedSteps,
            'first_run_completed_at' => now(),
        ])->save();

        $this->recordFirstRunEvent($state, (string) ($state->first_run_step_key ?? 'first_run'), 'first_run_completed');
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function buildFirstRunWizard(array $snapshot, TenantOnboardingState $state): array
    {
        $completedSteps = $this->normalizedCompletedFirstRunSteps($state, $snapshot);
        $steps = collect((array) ($snapshot['milestones'] ?? []))
            ->filter(static fn (mixed $milestone): bool => is_array($milestone) && filled($milestone['key'] ?? null))
            ->values();

        $currentStepKey = (string) ($state->first_run_step_key ?: '');
        $validStepKeys = $steps
            ->map(static fn (array $milestone): string => (string) ($milestone['key'] ?? ''))
            ->filter()
            ->values()
            ->all();

        if ($currentStepKey === '' || ! in_array($currentStepKey, $validStepKeys, true)) {
            $currentStepKey = collect($validStepKeys)
                ->first(fn (string $key): bool => ! in_array($key, $completedSteps, true))
                ?? (string) ($validStepKeys[0] ?? '');
        }

        $mappedSteps = $steps
            ->map(fn (array $milestone): array => [
                'key' => (string) ($milestone['key'] ?? ''),
                'title' => (string) ($milestone['title'] ?? 'Paso de activacion'),
                'copy' => (string) ($milestone['copy'] ?? ''),
                'href' => (string) ($milestone['href'] ?? '#'),
                'completed' => in_array((string) ($milestone['key'] ?? ''), $completedSteps, true),
                'current' => (string) ($milestone['key'] ?? '') === $currentStepKey,
            ])
            ->all();

        return [
            'progress' => $validStepKeys === []
                ? (int) ($snapshot['progress'] ?? 0)
                : (int) round((count($completedSteps) / max(1, count($validStepKeys))) * 100),
            'dismissed' => $state->first_run_dismissed_at instanceof Carbon,
            'completed' => $state->first_run_completed_at instanceof Carbon,
            'currentStepKey' => $currentStepKey !== '' ? $currentStepKey : null,
            'completedSteps' => array_values($completedSteps),
            'steps' => $mappedSteps,
            'startedAt' => $state->first_run_started_at,
            'completedAt' => $state->first_run_completed_at,
            'dismissedAt' => $state->first_run_dismissed_at,
            'abandonedAt' => $state->first_run_abandoned_at,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function emptyFirstRunWizard(array $snapshot): array
    {
        return [
            'progress' => (int) ($snapshot['progress'] ?? 0),
            'dismissed' => false,
            'completed' => false,
            'currentStepKey' => null,
            'completedSteps' => [],
            'steps' => [],
            'startedAt' => null,
            'completedAt' => null,
            'dismissedAt' => null,
            'abandonedAt' => null,
        ];
    }

    private function stateForSnapshot(Tenant $tenant, ?User $user, string $role): ?TenantOnboardingState
    {
        return TenantOnboardingState::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('user_id', $user?->getKey())
            ->where('role', $role)
            ->first();
    }

    /** @param list<array<string, mixed>> $milestones */
    private function firstIncompleteMilestoneKey(array $milestones): ?string
    {
        $milestone = collect($milestones)
            ->first(static fn (array $item): bool => ($item['completed'] ?? false) !== true);

        return is_array($milestone) ? (string) ($milestone['key'] ?? '') : null;
    }

    /**
     * @param list<array<string, mixed>> $milestones
     * @return list<string>
     */
    private function firstRunStepKeys(array $milestones): array
    {
        return collect($milestones)
            ->map(static fn (array $milestone): string => (string) ($milestone['key'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $snapshot */
    private function normalizedCompletedFirstRunSteps(TenantOnboardingState $state, array $snapshot): array
    {
        $persisted = collect((array) ($state->first_run_completed_steps ?? []))
            ->map(static fn (mixed $key): string => (string) $key)
            ->filter()
            ->values();

        $realCompleted = collect((array) ($snapshot['milestones'] ?? []))
            ->filter(static fn (mixed $milestone): bool => is_array($milestone) && ($milestone['completed'] ?? false) === true)
            ->map(static fn (array $milestone): string => (string) ($milestone['key'] ?? ''))
            ->filter()
            ->values();

        return $persisted
            ->merge($realCompleted)
            ->unique()
            ->values()
            ->all();
    }

    private function hasFirstRunWizardColumns(): bool
    {
        static $hasColumns = null;

        if ($hasColumns !== null) {
            return $hasColumns;
        }

        if (! $this->schema()->hasTable('tenant_onboarding_states')) {
            return $hasColumns = false;
        }

        return $hasColumns = $this->schema()->hasColumn('tenant_onboarding_states', 'first_run_step_key')
            && $this->schema()->hasColumn('tenant_onboarding_states', 'first_run_completed_steps')
            && $this->schema()->hasColumn('tenant_onboarding_states', 'first_run_abandoned_at');
    }

    private function recordFirstRunEvent(TenantOnboardingState $state, string $stepKey, string $eventType): void
    {
        if (! $this->schema()->hasTable('tenant_onboarding_milestone_events')) {
            return;
        }

        $this->db()->table('tenant_onboarding_milestone_events')->insert([
            'tenant_id' => (string) $state->tenant_id,
            'user_id' => $state->user_id,
            'role' => (string) $state->role,
            'milestone_key' => $stepKey !== '' ? $stepKey : 'first_run',
            'event_type' => $eventType,
            'snapshot_progress' => (int) $state->progress,
            'occurred_at' => now(),
            'metadata' => json_encode(['source' => 'first_run_wizard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $snapshot */
    private function resolveLastCompletedAt(array $snapshot, ?TenantOnboardingState $existingState): ?Carbon
    {
        $currentCompletedKeys = $this->completedMilestoneKeys((array) ($snapshot['milestones'] ?? []));

        if ($currentCompletedKeys === []) {
            return $existingState?->last_completed_at;
        }

        if (! $existingState instanceof TenantOnboardingState) {
            return now();
        }

        $previousCompletedKeys = $this->completedMilestoneKeys((array) ($existingState->milestones ?? []));
        $hasNewCompletedMilestone = collect($currentCompletedKeys)
            ->diff($previousCompletedKeys)
            ->isNotEmpty();

        if ($existingState->last_completed_at instanceof Carbon && ! $hasNewCompletedMilestone) {
            return $existingState->last_completed_at;
        }

        return $hasNewCompletedMilestone ? now() : $existingState->last_completed_at;
    }

    /**
     * @param array<int|string, mixed> $milestones
     * @return list<string>
     */
    private function completedMilestoneKeys(array $milestones): array
    {
        return collect($milestones)
            ->filter(fn (mixed $milestone): bool => is_array($milestone) && ($milestone['completed'] ?? false) === true)
            ->map(fn (array $milestone): string => (string) ($milestone['key'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{key:string,title:string,copy:string,href:string,completed:bool}>
     */
    private function ownerMilestones(
        string $tenantSlug,
        string $businessType,
        string $commercialMode,
        bool $hasProfile,
        bool $hasCatalog,
        bool $hasSchedule,
        bool $hasContact,
        bool $isPublished,
        array $manifestOnboarding = [],
    ): array
    {
        $offerIsReady = $hasCatalog || ($commercialMode === 'consulting_leads' && $hasProfile && $hasContact);

        $offerTitle = match ($commercialMode) {
            'consulting_leads' => 'Consulta lista para captar prospectos',
            'appointments' => 'Primer servicio reservable',
            'hybrid' => 'Primer flujo comercial combinado',
            'products' => 'Primera oferta vendible',
            default => match ($businessType) {
            'food' => 'Primer plato o combo',
            'service' => 'Primer servicio reservable',
            'hybrid' => 'Primer flujo comercial combinado',
            default => 'Primer producto publicado',
            },
        };

        $offerCopy = match ($commercialMode) {
            'consulting_leads' => 'Deja claro que problema resuelves y activa WhatsApp o formulario para dar seguimiento en CRM.',
            'appointments' => 'Define un servicio con duracion, precio y disponibilidad para recibir reservas claras.',
            'hybrid' => 'Empieza con una oferta principal y activa productos o agenda solo donde ya tengas operacion real.',
            'products' => 'Publica productos con precio y canal de pedido para que el directorio no mande clientes a una vitrina vacia.',
            default => match ($businessType) {
            'food' => 'Carga al menos un plato con precio para que el cliente pueda decidir rapido.',
            'service' => 'Define un servicio con duracion, precio y disponibilidad para recibir solicitudes claras.',
            'hybrid' => 'Publica un producto o servicio base antes de invitar clientes al perfil.',
            default => 'Publica tu primera oferta para que el directorio no mande clientes a una vitrina vacia.',
            },
        };

        $scheduleCopy = match ($commercialMode) {
            'appointments' => 'Configura disponibilidad real para que las reservas no choquen con tu operacion.',
            'consulting_leads' => 'Configura ventanas de respuesta para convertir consultas sin enfriar leads.',
            'hybrid' => 'Define horarios y capacidad por flujo para evitar sobreventa o sobreagenda.',
            default => 'Define disponibilidad real para evitar friccion comercial.',
        };

        $manifestMilestones = $this->ownerMilestonesFromManifest(
            tenantSlug: $tenantSlug,
            businessType: $businessType,
            commercialMode: $commercialMode,
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
                $this->milestone('owner_catalog', $offerTitle, $offerIsReady, $offerCopy, $this->catalogHref($tenantSlug, $businessType, $commercialMode)),
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
            $this->milestone('owner_catalog', $offerTitle, $offerIsReady, $offerCopy, $this->catalogHref($tenantSlug, $businessType, $commercialMode)),
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
        string $commercialMode,
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
            ->map(function (mixed $check, int $index) use ($tenantSlug, $businessType, $commercialMode, $hasProfile, $hasCatalog, $hasSchedule, $hasContact, $isPublished): array {
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
                    $this->manifestStateHref($tenantSlug, $businessType, $commercialMode, $state),
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

    private function commercialMode(Tenant $tenant): string
    {
        $raw = $tenant->commercial_mode ?? null;
        if ($raw instanceof BackedEnum) {
            return (string) $raw->value;
        }

        return in_array($raw, ['products', 'appointments', 'consulting_leads', 'hybrid'], true)
            ? (string) $raw
            : ($this->businessType($tenant) === 'service' ? 'appointments' : 'products');
    }

    private function businessLabel(Tenant $tenant, string $businessType, string $commercialMode): string
    {
        $label = (string) data_get($this->manifestForTenant($tenant), 'presentation.label', '');

        if ($label !== '') {
            return $label;
        }

        return match ($commercialMode) {
            'products' => 'Productos y ventas',
            'appointments' => 'Servicios y citas',
            'consulting_leads' => 'Consultoria y prospectos',
            'hybrid' => 'Operacion comercial mixta',
            default => match ($businessType) {
            'food' => 'Restaurante y comida',
            'service' => 'Servicios y citas',
            'hybrid' => 'Negocio mixto',
            default => 'Operacion comercial',
            },
        };
    }

    /** @return list<array{title:string,copy:string}> */
    private function supportTips(string $businessType, string $commercialMode, int $progress, array $manifestOnboarding = []): array
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

        $specific = match ($commercialMode) {
            'products' => ['title' => 'Productos y ventas', 'copy' => 'Prioriza oferta accionable, precio y canal de pedido. El objetivo es convertir rapido, no solo exhibir catalogo.'],
            'appointments' => ['title' => 'Servicios y citas', 'copy' => 'Prioriza servicios, disponibilidad y confianza antes de abrir agenda publica. Reserva confirmada es la metrica principal.'],
            'consulting_leads' => ['title' => 'Consultoria y prospectos', 'copy' => 'Prioriza autoridad, CTA principal y seguimiento comercial. Un profesional no debe parecer tienda.'],
            'hybrid' => ['title' => 'Para flujo mixto', 'copy' => 'Activa productos y agenda por etapas. Si todo se abre sin operacion real, aumenta el soporte y baja la confianza.'],
            default => match ($businessType) {
            'food' => ['title' => 'Para comida', 'copy' => 'Prioriza menu, horarios y WhatsApp. Es lo que mas evita reclamos de clientes hambrientos.'],
            'service' => ['title' => 'Para servicios', 'copy' => 'Prioriza servicios, disponibilidad y confianza del perfil antes de abrir agenda.'],
            'hybrid' => ['title' => 'Para negocio mixto', 'copy' => 'Empieza con una oferta principal y luego activa productos, servicios o agenda segun demanda.'],
            default => ['title' => 'Para tienda', 'copy' => 'Prioriza oferta accionable, precio y contacto. Sin oferta visible el directorio no convierte.'],
            },
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

    private function manifestStateHref(string $tenantSlug, string $businessType, string $commercialMode, string $state): string
    {
        return match ($state) {
            'has_products', 'has_menu_items', 'has_products_or_services', 'products', 'services' => $this->catalogHref($tenantSlug, $businessType, $commercialMode),
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

    private function catalogHref(string $tenantSlug, string $businessType = 'retail', string $commercialMode = 'products'): string
    {
        if ($tenantSlug === '') {
            return '#';
        }

        if ($commercialMode === 'consulting_leads') {
            return $this->settingsHref($tenantSlug);
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
            ->exists() || $tenant->appointments()->withoutGlobalScopes()->exists();
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

    private function db(): \Illuminate\Database\ConnectionInterface
    {
        return app('db')->connection();
    }

    private function schema(): \Illuminate\Database\Schema\Builder
    {
        return $this->db()->getSchemaBuilder();
    }

    /** @return array<string, mixed> */
    private function manifestForTenant(Tenant $tenant): array
    {
        if (! interface_exists(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class)) {
            return [];
        }

        try {
            $reader = app(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class);

            return is_object($reader) && method_exists($reader, 'forTenant')
                ? (array) $reader->forTenant($tenant)
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private function manifestOnboardingForTenant(Tenant $tenant): array
    {
        if (! interface_exists(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class)) {
            return [];
        }

        try {
            $reader = app(\App\Application\TenantGovernance\Contracts\RubroManifestReaderInterface::class);

            return is_object($reader) && method_exists($reader, 'onboardingForTenant')
                ? (array) $reader->onboardingForTenant($tenant)
                : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
