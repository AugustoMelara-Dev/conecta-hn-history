<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Domain\TenantGovernance\Services\CustomerConversationTemplateManager;
use App\Domain\TenantGovernance\Services\CustomerConversationThreadBuilder;
use App\Domain\TenantGovernance\Services\CustomerSegmentPlaybook;
use App\Models\CustomerConversation;
use App\Filament\App\Resources\CustomerProfileResource;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CustomerInbox extends Page
{
    protected static bool $isDiscovered = false;

    protected static string $routePath = 'customer-inbox';

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Respuestas';

    protected static ?string $title = 'Bandeja de respuestas';

    protected string $view = 'filament.app.pages.customer-inbox';

    public string $tenantName = 'Tu negocio';

    public int $openCount = 0;

    public int $inProgressCount = 0;

    public int $waitingCustomerCount = 0;

    public int $resolvedTodayCount = 0;

    public int $urgentCount = 0;

    public int $breachedCount = 0;

    public int $myQueueCount = 0;

    public int $dueTodayCount = 0;

    public int $unassignedCount = 0;

    public string $selectedScope = 'all';

    public string $selectedQueueLane = 'all';

    public string $selectedChannel = 'all';

    public ?string $selectedOwnerId = null;

    public ?string $selectedLocationId = null;

    public string $selectedSlaState = 'all';

    public ?int $selectedConversationId = null;

    public string $draftInternalNote = '';

    /** @var array<string, string> */
    public array $teamOptions = [];

    /** @var array<string, string> */
    public array $locationOptions = [];

    /** @var list<array{label:string,total:int,waiting:int,breached:int}> */
    public array $channelOverview = [];

    /** @var list<array{name:string,total:int,waiting:int,breached:int}> */
    public array $ownerOverview = [];

    /** @var list<array{name:string,total:int,waiting:int,breached:int}> */
    public array $locationOverview = [];

    /** @var list<array{id:int,conversationId:?int,title:string,body:string,status:string,statusLabel:string,priority:string,priorityLabel:string,channel:string,channelLabel:string,customerName:string,segmentLabel:string,loyaltyTier:string,nextTouch:string,loyaltyMove:string,handledBy:?string,assignedTo:?string,assignedUserId:?string,locationName:string,locationId:?string,nextActionAt:?string,profileUrl:string,slaLabel:string,slaColor:string,replyCount:int,responseDueAt:?string}> */
    public array $queue = [];

    /** @var array<string, mixed> */
    public array $selectedConversation = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Ventas';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Filament::getTenant() instanceof Tenant;
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $count = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('requires_follow_up', true)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->tenantName = (string) $tenant->name;
        $this->teamOptions = $this->resolveTeamOptions($tenant);
        $this->locationOptions = $this->resolveLocationOptions($tenant);
        $this->refreshQueue();
    }

    public function updatedSelectedScope(): void
    {
        $this->refreshQueue();
    }

    public function updatedSelectedQueueLane(): void
    {
        $this->refreshQueue();
    }

    public function updatedSelectedChannel(): void
    {
        $this->refreshQueue();
    }

    public function updatedSelectedOwnerId(): void
    {
        $this->refreshQueue();
    }

    public function updatedSelectedLocationId(): void
    {
        $this->refreshQueue();
    }

    public function updatedSelectedSlaState(): void
    {
        $this->refreshQueue();
    }

    public function selectConversation(int $noteId): void
    {
        $this->selectedConversationId = $noteId;
        $this->refreshSelectedConversation();
    }

    public function take(int $noteId): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        app(CustomerConversationOpsManager::class)->take($note, auth()->id());
        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Caso tomado')
            ->success()
            ->body('La respuesta ya quedo en tu cola de seguimiento.')
            ->send();
    }

    public function resolve(int $noteId): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        app(CustomerConversationOpsManager::class)->resolve($note, auth()->id());
        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Caso resuelto')
            ->success()
            ->body('La respuesta ya salio de la cola activa.')
            ->send();
    }

    public function reassignOwner(int $noteId, ?string $userId = null): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        $normalizedUserId = filled($userId) ? (string) $userId : null;
        app(CustomerConversationOpsManager::class)->reassign($note, $normalizedUserId, auth()->id());
        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Owner actualizado')
            ->success()
            ->body($normalizedUserId !== null ? 'La conversacion ya cambio de responsable.' : 'La conversacion quedo sin owner asignado.')
            ->send();
    }

    public function moveLocation(int $noteId, ?string $locationId = null): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        $normalizedLocationId = filled($locationId) ? (string) $locationId : null;
        app(CustomerConversationOpsManager::class)->moveToLocation($note, $normalizedLocationId, auth()->id());
        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Sede actualizada')
            ->success()
            ->body($normalizedLocationId !== null ? 'La conversacion ya esta ligada a otra sede.' : 'La conversacion quedo sin sede asignada.')
            ->send();
    }

    public function sendSuggestedReply(int $noteId, string $templateKey): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        $profile = $note->customerProfile()
            ->withoutGlobalScopes()
            ->with(['assignedUser', 'location', 'tenant'])
            ->first();

        if (! $profile instanceof CustomerProfile) {
            Notification::make()
                ->title('No se pudo enviar')
                ->danger()
                ->body('La conversacion no tiene un cliente valido ligado al CRM.')
                ->send();

            return;
        }

        try {
            $touchpoint = app(CustomerConversationTemplateManager::class)->send(
                profile: $profile,
                templateKey: $templateKey,
                note: $note,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            );
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('No se pudo enviar')
                ->danger()
                ->body($exception->getMessage())
                ->send();

            return;
        }

        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        $title = $touchpoint->status === 'sent' ? 'Respuesta enviada' : 'Plantilla procesada';
        $body = $touchpoint->status === 'sent'
            ? 'La conversacion ya quedo en espera del cliente con seguimiento registrado.'
            : ((string) ($touchpoint->last_error ?? 'La plantilla no se pudo entregar como envio real.'));

        Notification::make()
            ->title($title)
            ->success()
            ->body($body)
            ->send();
    }

    public function addInternalNote(int $noteId): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        $entry = app(CustomerConversationOpsManager::class)->addInternalNote(
            $note,
            $this->draftInternalNote,
            auth()->id() !== null ? (string) auth()->id() : null,
        );

        if ($entry === null) {
            Notification::make()
                ->title('No se pudo guardar')
                ->warning()
                ->body('Escribe una nota interna antes de guardarla.')
                ->send();

            return;
        }

        $this->draftInternalNote = '';
        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Nota interna guardada')
            ->success()
            ->body('La nota ya quedo separada del hilo del cliente y visible para el equipo.')
            ->send();
    }

    private function refreshQueue(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            $this->queue = [];
            $this->selectedConversation = [];
            $this->selectedConversationId = null;
            $this->channelOverview = [];
            $this->ownerOverview = [];
            $this->locationOverview = [];

            return;
        }

        $tenantId = (string) $tenant->getKey();
        $conversationBase = CustomerConversation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        $activeConversations = (clone $conversationBase)
            ->with([
                'owner' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'journeyRun',
            ])
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->get();

        $this->openCount = (clone $conversationBase)->where('status', 'open')->count();
        $this->inProgressCount = (clone $conversationBase)->where('status', 'in_progress')->count();
        $this->waitingCustomerCount = (clone $conversationBase)->where('status', 'waiting_customer')->count();
        $this->resolvedTodayCount = (clone $conversationBase)
            ->where('status', 'closed')
            ->where('closed_at', '>=', now()->startOfDay())
            ->count();
        $this->urgentCount = (clone $conversationBase)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->where('priority', 'urgent')
            ->count();
        $this->breachedCount = (clone $conversationBase)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->where(function ($query): void {
                $query->whereNotNull('response_due_at')->where('response_due_at', '<', now());
            })
            ->count();
        $this->myQueueCount = auth()->check()
            ? (clone $conversationBase)
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                ->where('owner_user_id', (string) auth()->id())
                ->count()
            : 0;
        $this->dueTodayCount = (clone $conversationBase)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->whereNotNull('response_due_at')
            ->whereBetween('response_due_at', [now(), now()->endOfDay()])
            ->count();
        $this->unassignedCount = (clone $conversationBase)
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
            ->whereNull('owner_user_id')
            ->count();

        $this->channelOverview = $this->buildConversationOverviewRows($activeConversations, 'source_channel', [
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'crm' => 'CRM',
        ]);
        $this->ownerOverview = $this->buildConversationOverviewRows($activeConversations, 'owner_user_id');
        $this->locationOverview = $this->buildConversationOverviewRows($activeConversations, 'location_id');

        $baseQuery = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->where(function ($legacy): void {
                    $legacy->where('requires_follow_up', true)
                        ->whereIn('status', ['open', 'in_progress']);
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()
                        ->whereIn('status', ['open', 'in_progress', 'waiting_customer']);
                });
            });

        $playbooks = app(CustomerSegmentPlaybook::class);
        $queueQuery = $this->applyQueueFilters(clone $baseQuery);

        $this->queue = $queueQuery
            ->with([
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with(['assignedUser', 'location']),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
                'assignedUser' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'owner' => fn ($owner) => $owner->withoutGlobalScopes(),
                    'location' => fn ($location) => $location->withoutGlobalScopes(),
                    'journeyRun',
                ]),
            ])
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('response_due_at')
            ->orderBy('next_action_at')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (CustomerNote $note) use ($playbooks, $tenant): array {
                $profile = $note->customerProfile()->withoutGlobalScopes()->with(['assignedUser', 'location'])->first();
                $conversation = $note->conversation;
                $playbook = $profile !== null ? $playbooks->forProfile($profile) : null;
                $journeyRun = $conversation?->journeyRun;
                $recommendedTemplateKey = data_get($journeyRun?->metadata, 'recommended_template_key');

                return [
                    'id' => (int) $note->getKey(),
                    'conversationId' => $conversation?->getKey(),
                    'title' => $note->note_type_label,
                    'body' => (string) $note->body,
                    'status' => (string) ($conversation?->status ?? $note->status),
                    'statusLabel' => $conversation?->status_label ?? $note->status_label,
                    'priority' => (string) ($conversation?->priority ?? $note->priority),
                    'priorityLabel' => $conversation?->priority_label ?? $note->priority_label,
                    'channel' => (string) ($conversation?->source_channel ?? $note->source_channel ?? 'system'),
                    'channelLabel' => $this->formatChannelLabel((string) ($conversation?->source_channel ?? $note->source_channel ?? 'system')),
                    'customerName' => (string) ($profile?->name ?? 'Cliente sin nombre'),
                    'segmentLabel' => (string) ($playbook['label'] ?? 'Sin segmentar'),
                    'loyaltyTier' => (string) ($profile?->loyalty_tier_label ?? 'Base'),
                    'nextTouch' => (string) ($playbook['next_touch'] ?? 'Conviene responder en este turno.'),
                    'loyaltyMove' => (string) ($playbook['loyalty_move'] ?? 'Mantener seguimiento simple.'),
                    'handledBy' => $note->handledBy?->name,
                    'assignedTo' => $conversation?->owner?->name ?? $note->assignedUser?->name ?? $profile?->assigned_owner_label,
                    'assignedUserId' => $conversation?->owner_user_id !== null
                        ? (string) $conversation->owner_user_id
                        : ($note->assigned_user_id !== null ? (string) $note->assigned_user_id : ($profile?->assigned_user_id !== null ? (string) $profile->assigned_user_id : null)),
                    'locationName' => $conversation?->location?->name ?? $note->location?->name ?? $profile?->location_label ?? 'Sin sede',
                    'locationId' => $conversation?->location_id !== null
                        ? (string) $conversation->location_id
                        : ($note->location_id !== null ? (string) $note->location_id : ($profile?->location_id !== null ? (string) $profile->location_id : null)),
                    'nextActionAt' => ($conversation?->next_action_at ?? $note->next_action_at)?->diffForHumans(),
                    'slaLabel' => $conversation?->sla_label ?? $note->sla_label,
                    'slaColor' => $conversation?->sla_color ?? $note->sla_color,
                    'replyCount' => max(1, (int) ($conversation?->reply_count ?? $note->reply_count)),
                    'responseDueAt' => ($conversation?->response_due_at ?? $note->response_due_at)?->diffForHumans(),
                    'queueLane' => (string) data_get($journeyRun?->metadata, 'lane', 'follow_up'),
                    'sequenceStateLabel' => $journeyRun?->sequence_state_label ?? 'Listo para mover',
                    'dueStateLabel' => $journeyRun?->due_state_label ?? $conversation?->sla_label ?? $note->sla_label,
                    'recommendedTemplateLabel' => filled($recommendedTemplateKey)
                        ? str((string) $recommendedTemplateKey)->replace('_', ' ')->replace('-', ' ')->title()->value()
                        : 'Sin recomendacion',
                    'journeyStep' => data_get($journeyRun?->metadata, 'current_step.title'),
                    'profileUrl' => $profile !== null
                        ? CustomerProfileResource::getUrl('edit', [
                            'record' => $profile,
                            'tenant' => $tenant->slug,
                        ])
                        : '#',
                ];
            })
            ->values()
            ->all();

        $this->refreshSelectedConversation();
    }

    private function refreshSelectedConversation(): void
    {
        if ($this->selectedConversationId === null && $this->queue !== []) {
            $this->selectedConversationId = (int) $this->queue[0]['id'];
        }

        if ($this->selectedConversationId === null) {
            $this->selectedConversation = [];

            return;
        }

        $note = $this->findTenantNote($this->selectedConversationId);

        if (! $note instanceof CustomerNote) {
            $this->selectedConversation = [];
            $this->selectedConversationId = $this->queue !== [] ? (int) $this->queue[0]['id'] : null;

            if ($this->selectedConversationId !== null) {
                $this->refreshSelectedConversation();
            }

            return;
        }

        $this->selectedConversation = $this->buildConversationWorkspace($note);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConversationWorkspace(CustomerNote $note): array
    {
        $tenant = Filament::getTenant();
        $profile = $note->customerProfile()
            ->withoutGlobalScopes()
            ->with(['assignedUser', 'location', 'tenant'])
            ->first();
        $conversation = $note->conversation()
            ->withoutGlobalScopes()
            ->with([
                'owner' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'journeyRun',
            ])
            ->first();

        if (! $tenant instanceof Tenant || ! $profile instanceof CustomerProfile) {
            return [];
        }

        $playbook = app(CustomerSegmentPlaybook::class)->forProfile($profile);
        $thread = app(CustomerConversationThreadBuilder::class)->build(
            $profile,
            $note,
            max(8, (int) config('customer-conversations.defaults.thread_limit', 16)),
        );
        $journey = app(CustomerConversationTemplateManager::class)->forConversation($profile, $note);
        $task = $note->task()->withoutGlobalScopes()->with(['assignedUser', 'location'])->first();
        $journeyRun = $conversation?->journeyRun;

        return [
            'id' => (int) $note->getKey(),
            'conversationId' => $conversation?->getKey(),
            'title' => $note->note_type_label,
            'body' => (string) $note->body,
            'status' => (string) ($conversation?->status ?? $note->status),
            'statusLabel' => $conversation?->status_label ?? $note->status_label,
            'priorityLabel' => $conversation?->priority_label ?? $note->priority_label,
            'channelLabel' => $this->formatChannelLabel((string) ($conversation?->source_channel ?? $note->source_channel ?? 'crm')),
            'customerName' => (string) ($profile->name ?? 'Cliente sin nombre'),
            'segmentLabel' => (string) ($playbook['label'] ?? $profile->segment_display_label),
            'segmentPromise' => (string) ($playbook['promise'] ?? 'Cliente en seguimiento.'),
            'nextTouch' => (string) ($playbook['next_touch'] ?? 'Conviene responder en este turno.'),
            'loyaltyMove' => (string) ($playbook['loyalty_move'] ?? 'Conviene mover continuidad.'),
            'loyaltyTier' => (string) $profile->loyalty_tier_label,
            'slaLabel' => $conversation?->sla_label ?? $note->sla_label,
            'slaColor' => $conversation?->sla_color ?? $note->sla_color,
            'ownerLabel' => $conversation?->owner?->name ?? $note->assignedUser?->name ?? $profile->assigned_owner_label,
            'locationLabel' => $conversation?->location?->name ?? $note->location?->name ?? $profile->location_label,
            'lastActionAt' => ($conversation?->next_action_at ?? $note->next_action_at)?->diffForHumans(),
            'responseDueAt' => ($conversation?->response_due_at ?? $note->response_due_at)?->diffForHumans(),
            'replyCount' => max(1, (int) ($conversation?->reply_count ?? $note->reply_count)),
            'profileUrl' => CustomerProfileResource::getUrl('edit', [
                'record' => $profile,
                'tenant' => $tenant->slug,
            ]),
            'threadSummary' => $thread['summary'],
            'threadItems' => $thread['items'],
            'internalNotes' => $thread['internal_notes'],
            'journey' => $journey['journey'],
            'journeyRun' => $journeyRun instanceof CustomerJourneyRun ? [
                'statusLabel' => $journeyRun->status_label,
                'sequenceStateLabel' => $journeyRun->sequence_state_label,
                'dueStateLabel' => $journeyRun->due_state_label,
                'currentStepIndex' => (int) $journeyRun->current_step_index,
                'nextActionAt' => $journeyRun->next_action_at?->diffForHumans(),
                'stepDueAt' => $journeyRun->step_due_at?->diffForHumans(),
                'currentStep' => data_get($journeyRun->metadata, 'current_step.title'),
                'currentStepCopy' => data_get($journeyRun->metadata, 'current_step.copy'),
                'currentLane' => data_get($journeyRun->metadata, 'lane'),
                'nextStep' => data_get($journeyRun->metadata, 'next_step.title'),
                'nextTemplateKey' => $journeyRun->next_template_key,
                'recommendedTemplateKey' => data_get($journeyRun->metadata, 'recommended_template_key'),
            ] : null,
            'currentStep' => $journey['current_step'],
            'nextStep' => $journey['next_step'],
            'templates' => $journey['templates'],
            'task' => $task instanceof CustomerTask ? [
                'title' => (string) $task->title,
                'statusLabel' => $task->status_label,
                'priorityLabel' => $task->priority_label,
                'slaLabel' => $task->sla_label,
                'ownerLabel' => $task->assignedUser?->name ?? $profile->assigned_owner_label,
                'locationLabel' => $task->location?->name ?? $profile->location_label,
                'dueAt' => $task->due_at?->diffForHumans(),
                'slaDueAt' => $task->sla_due_at?->diffForHumans(),
            ] : null,
        ];
    }

    private function applyQueueFilters($query)
    {
        if ($this->selectedScope === 'mine' && auth()->check()) {
            $query->where(function ($mine): void {
                $mine->where('assigned_user_id', (string) auth()->id())
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()
                            ->where('owner_user_id', (string) auth()->id());
                    });
            });
        } elseif ($this->selectedScope === 'unassigned') {
            $query->where(function ($unassigned): void {
                $unassigned->whereNull('assigned_user_id')
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()
                            ->whereNull('owner_user_id');
                    });
            });
        }

        if (filled($this->selectedOwnerId)) {
            $query->where(function ($owner): void {
                $owner->where('assigned_user_id', (string) $this->selectedOwnerId)
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()
                            ->where('owner_user_id', (string) $this->selectedOwnerId);
                    });
            });
        }

        if (filled($this->selectedLocationId)) {
            $query->where(function ($location): void {
                $location->where('location_id', (string) $this->selectedLocationId)
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()
                            ->where('location_id', (string) $this->selectedLocationId);
                    });
            });
        }

        if ($this->selectedChannel !== 'all') {
            $query->where(function ($channel): void {
                $channel->where('source_channel', $this->selectedChannel)
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()
                            ->where('source_channel', $this->selectedChannel);
                    });
            });
        }

        if ($this->selectedQueueLane === 'waiting_customer') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->where('status', 'waiting_customer');
            });
        } elseif ($this->selectedQueueLane === 'stalled') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->whereHas('journeyRun', function ($journey): void {
                    $journey->withoutGlobalScopes()->where('sequence_state', 'stalled');
                });
            });
        } elseif ($this->selectedQueueLane === 'closing') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->whereHas('journeyRun', function ($journey): void {
                    $journey->withoutGlobalScopes()->where('current_step_key', 'like', '%close%');
                });
            });
        } elseif ($this->selectedQueueLane === 'active') {
            $query->where(function ($active): void {
                $active->whereIn('status', ['open', 'in_progress'])
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()->whereIn('status', ['open', 'in_progress']);
                    });
            });
        }

        if ($this->selectedSlaState === 'breached') {
            $query->where(function ($breached): void {
                $breached->where(function ($noteBreached): void {
                    $noteBreached->whereNotNull('response_due_at')->where('response_due_at', '<', now());
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()
                        ->whereNotNull('response_due_at')
                        ->where('response_due_at', '<', now());
                });
            });
        } elseif ($this->selectedSlaState === 'today') {
            $query->where(function ($today): void {
                $today->where(function ($noteToday): void {
                    $noteToday->whereNotNull('response_due_at')->whereBetween('response_due_at', [now(), now()->endOfDay()]);
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()
                        ->whereNotNull('response_due_at')
                        ->whereBetween('response_due_at', [now(), now()->endOfDay()]);
                });
            });
        } elseif ($this->selectedSlaState === 'healthy') {
            $query->where(function ($healthy): void {
                $healthy->where(function ($noteHealthy): void {
                    $noteHealthy->whereNull('response_due_at')->orWhere('response_due_at', '>=', now());
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()->where(function ($conversationHealthy): void {
                        $conversationHealthy->whereNull('response_due_at')->orWhere('response_due_at', '>=', now());
                    });
                });
            });
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CustomerConversation>  $conversations
     * @param  array<string, string>  $labels
     * @return list<array{name:string,label:string,total:int,waiting:int,breached:int}>
     */
    private function buildConversationOverviewRows($conversations, string $groupBy, array $labels = []): array
    {
        return $conversations
            ->groupBy(fn (CustomerConversation $conversation): string => (string) ($conversation->{$groupBy} ?? ''))
            ->map(function ($items, string $key) use ($groupBy, $labels): array {
                /** @var \Illuminate\Support\Collection<int, CustomerConversation> $items */
                $name = match ($groupBy) {
                    'source_channel' => $labels[$key] ?? $this->formatChannelLabel($key),
                    'owner_user_id' => $items->first()?->owner?->name ?? 'Sin owner',
                    'location_id' => $items->first()?->location?->name ?? 'Sin sede',
                    default => $labels[$key] ?? ($key !== '' ? $key : 'Sin contexto'),
                };

                return [
                    'name' => $name,
                    'label' => $name,
                    'total' => $items->count(),
                    'waiting' => $items->where('status', 'waiting_customer')->count(),
                    'breached' => $items->filter(static fn (CustomerConversation $conversation): bool => $conversation->response_due_at !== null && $conversation->response_due_at->isPast())->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(4)
            ->values()
            ->all();
    }

    private function formatChannelLabel(string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'crm' => 'CRM',
            default => str($channel)->replace('_', ' ')->replace('-', ' ')->title()->value(),
        };
    }

    private function findTenantNote(int $noteId): ?CustomerNote
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->with([
                'assignedUser' => fn ($query) => $query->withoutGlobalScopes(),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'owner' => fn ($owner) => $owner->withoutGlobalScopes(),
                    'location' => fn ($location) => $location->withoutGlobalScopes(),
                    'journeyRun',
                ]),
            ])
            ->find($noteId);
    }

    /**
     * @return array<string, string>
     */
    private function resolveTeamOptions(Tenant $tenant): array
    {
        $userIds = TenantUser::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->pluck('user_id')
            ->push((string) $tenant->user_id)
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $userIds->all())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function resolveLocationOptions(Tenant $tenant): array
    {
        return Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }
}
