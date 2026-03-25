<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Services\CustomerConversationOpsManager;
use App\Domain\TenantGovernance\Services\CustomerConversationTemplateManager;
use App\Domain\TenantGovernance\Services\CustomerConversationThreadBuilder;
use App\Domain\TenantGovernance\Services\CustomerSegmentPlaybook;
use App\Filament\App\Resources\CustomerProfileResource;
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

    public int $resolvedTodayCount = 0;

    public int $urgentCount = 0;

    public int $breachedCount = 0;

    public int $myQueueCount = 0;

    public int $dueTodayCount = 0;

    public int $unassignedCount = 0;

    public string $selectedScope = 'all';

    public ?string $selectedOwnerId = null;

    public ?string $selectedLocationId = null;

    public string $selectedSlaState = 'all';

    public ?int $selectedConversationId = null;

    /** @var array<string, string> */
    public array $teamOptions = [];

    /** @var array<string, string> */
    public array $locationOptions = [];

    /** @var list<array{id:int,title:string,body:string,status:string,statusLabel:string,priority:string,priorityLabel:string,channel:string,channelLabel:string,customerName:string,segmentLabel:string,loyaltyTier:string,nextTouch:string,loyaltyMove:string,handledBy:?string,assignedTo:?string,assignedUserId:?string,locationName:string,locationId:?string,nextActionAt:?string,profileUrl:string,slaLabel:string,slaColor:string,replyCount:int,responseDueAt:?string}> */
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

    private function refreshQueue(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            $this->queue = [];
            $this->selectedConversation = [];
            $this->selectedConversationId = null;

            return;
        }

        $tenantId = (string) $tenant->getKey();

        $baseQuery = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('requires_follow_up', true);

        $this->openCount = (clone $baseQuery)->where('status', 'open')->count();
        $this->inProgressCount = (clone $baseQuery)->where('status', 'in_progress')->count();
        $this->resolvedTodayCount = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'resolved')
            ->where('handled_at', '>=', now()->startOfDay())
            ->count();
        $this->urgentCount = (clone $baseQuery)->where('priority', 'urgent')->count();
        $this->breachedCount = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('response_due_at')
            ->where('response_due_at', '<', now())
            ->count();
        $this->myQueueCount = auth()->check()
            ? (clone $baseQuery)->where('assigned_user_id', (string) auth()->id())->whereIn('status', ['open', 'in_progress'])->count()
            : 0;
        $this->dueTodayCount = CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('response_due_at')
            ->whereBetween('response_due_at', [now(), now()->endOfDay()])
            ->count();
        $this->unassignedCount = (clone $baseQuery)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNull('assigned_user_id')
            ->count();

        $playbooks = app(CustomerSegmentPlaybook::class);
        $queueQuery = $this->applyQueueFilters(clone $baseQuery);

        $this->queue = $queueQuery
            ->with([
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with(['assignedUser', 'location']),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
                'assignedUser' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('response_due_at')
            ->orderBy('next_action_at')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (CustomerNote $note) use ($playbooks, $tenant): array {
                $profile = $note->customerProfile()->withoutGlobalScopes()->with(['assignedUser', 'location'])->first();
                $playbook = $profile !== null ? $playbooks->forProfile($profile) : null;

                return [
                    'id' => (int) $note->getKey(),
                    'title' => $note->note_type_label,
                    'body' => (string) $note->body,
                    'status' => (string) $note->status,
                    'statusLabel' => $note->status_label,
                    'priority' => (string) $note->priority,
                    'priorityLabel' => $note->priority_label,
                    'channel' => (string) ($note->source_channel ?? 'system'),
                    'channelLabel' => $note->source_channel_label,
                    'customerName' => (string) ($profile?->name ?? 'Cliente sin nombre'),
                    'segmentLabel' => (string) ($playbook['label'] ?? 'Sin segmentar'),
                    'loyaltyTier' => (string) ($profile?->loyalty_tier_label ?? 'Base'),
                    'nextTouch' => (string) ($playbook['next_touch'] ?? 'Conviene responder en este turno.'),
                    'loyaltyMove' => (string) ($playbook['loyalty_move'] ?? 'Mantener seguimiento simple.'),
                    'handledBy' => $note->handledBy?->name,
                    'assignedTo' => $note->assignedUser?->name ?? $profile?->assigned_owner_label,
                    'assignedUserId' => $note->assigned_user_id !== null ? (string) $note->assigned_user_id : ($profile?->assigned_user_id !== null ? (string) $profile->assigned_user_id : null),
                    'locationName' => $note->location?->name ?? $profile?->location_label ?? 'Sin sede',
                    'locationId' => $note->location_id !== null ? (string) $note->location_id : ($profile?->location_id !== null ? (string) $profile->location_id : null),
                    'nextActionAt' => $note->next_action_at?->diffForHumans(),
                    'slaLabel' => $note->sla_label,
                    'slaColor' => $note->sla_color,
                    'replyCount' => max(1, (int) $note->reply_count),
                    'responseDueAt' => $note->response_due_at?->diffForHumans(),
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

        return [
            'id' => (int) $note->getKey(),
            'title' => $note->note_type_label,
            'body' => (string) $note->body,
            'status' => (string) $note->status,
            'statusLabel' => $note->status_label,
            'priorityLabel' => $note->priority_label,
            'channelLabel' => $note->source_channel_label,
            'customerName' => (string) ($profile->name ?? 'Cliente sin nombre'),
            'segmentLabel' => (string) ($playbook['label'] ?? $profile->segment_display_label),
            'segmentPromise' => (string) ($playbook['promise'] ?? 'Cliente en seguimiento.'),
            'nextTouch' => (string) ($playbook['next_touch'] ?? 'Conviene responder en este turno.'),
            'loyaltyMove' => (string) ($playbook['loyalty_move'] ?? 'Conviene mover continuidad.'),
            'loyaltyTier' => (string) $profile->loyalty_tier_label,
            'slaLabel' => $note->sla_label,
            'slaColor' => $note->sla_color,
            'ownerLabel' => $note->assignedUser?->name ?? $profile->assigned_owner_label,
            'locationLabel' => $note->location?->name ?? $profile->location_label,
            'lastActionAt' => $note->next_action_at?->diffForHumans(),
            'responseDueAt' => $note->response_due_at?->diffForHumans(),
            'replyCount' => max(1, (int) $note->reply_count),
            'profileUrl' => CustomerProfileResource::getUrl('edit', [
                'record' => $profile,
                'tenant' => $tenant->slug,
            ]),
            'threadSummary' => $thread['summary'],
            'threadItems' => $thread['items'],
            'journey' => $journey['journey'],
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
            $query->where('assigned_user_id', (string) auth()->id());
        } elseif ($this->selectedScope === 'unassigned') {
            $query->whereNull('assigned_user_id');
        }

        if (filled($this->selectedOwnerId)) {
            $query->where('assigned_user_id', (string) $this->selectedOwnerId);
        }

        if (filled($this->selectedLocationId)) {
            $query->where('location_id', (string) $this->selectedLocationId);
        }

        if ($this->selectedSlaState === 'breached') {
            $query->whereNotNull('response_due_at')->where('response_due_at', '<', now());
        } elseif ($this->selectedSlaState === 'today') {
            $query->whereNotNull('response_due_at')->whereBetween('response_due_at', [now(), now()->endOfDay()]);
        } elseif ($this->selectedSlaState === 'healthy') {
            $query->where(function ($healthy): void {
                $healthy->whereNull('response_due_at')->orWhere('response_due_at', '>=', now());
            });
        }

        return $query;
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
