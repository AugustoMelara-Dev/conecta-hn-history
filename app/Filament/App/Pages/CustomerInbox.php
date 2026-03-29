<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Application\TenantGovernance\Inbox\Commands\AddCustomerInboxInternalNoteCommand;
use App\Application\TenantGovernance\Inbox\Commands\MoveCustomerInboxConversationLocationCommand;
use App\Application\TenantGovernance\Inbox\Commands\ReassignCustomerInboxConversationCommand;
use App\Application\TenantGovernance\Inbox\Commands\ResolveCustomerInboxConversationCommand;
use App\Application\TenantGovernance\Inbox\Commands\RouteCustomerInboxConversationGroupCommand;
use App\Application\TenantGovernance\Inbox\Commands\SendCustomerInboxTemplateCommand;
use App\Application\TenantGovernance\Inbox\Commands\TakeCustomerInboxConversationCommand;
use App\Application\TenantGovernance\Inbox\Handlers\AddCustomerInboxInternalNoteHandler;
use App\Application\TenantGovernance\Inbox\Handlers\MoveCustomerInboxConversationLocationHandler;
use App\Application\TenantGovernance\Inbox\Handlers\ReassignCustomerInboxConversationHandler;
use App\Application\TenantGovernance\Inbox\Handlers\ResolveCustomerInboxConversationHandler;
use App\Application\TenantGovernance\Inbox\Handlers\RouteCustomerInboxConversationGroupHandler;
use App\Application\TenantGovernance\Inbox\Handlers\SendCustomerInboxTemplateHandler;
use App\Application\TenantGovernance\Inbox\Handlers\TakeCustomerInboxConversationHandler;
use App\Domain\TenantGovernance\Services\CustomerConversationRouting;
use App\Infrastructure\TenantGovernance\Inbox\CustomerInboxReadModel;
use App\Models\CustomerNote;
use App\Models\Tenant;
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

    public string $selectedAssignmentGroup = 'all';

    public string $selectedSlaState = 'all';

    public ?int $selectedConversationId = null;

    public string $draftInternalNote = '';

    /** @var array<string, string> */
    public array $teamOptions = [];

    /** @var array<string, string> */
    public array $locationOptions = [];

    /** @var array<string, string> */
    public array $assignmentGroupOptions = [];

    /** @var list<array{label:string,total:int,waiting:int,breached:int}> */
    public array $channelOverview = [];

    /** @var list<array{name:string,label:string,total:int,waiting:int,breached:int}> */
    public array $assignmentGroupOverview = [];

    /** @var list<array{name:string,total:int,waiting:int,breached:int}> */
    public array $ownerOverview = [];

    /** @var list<array{name:string,total:int,waiting:int,breached:int}> */
    public array $locationOverview = [];

    /** @var list<array{id:int,conversationId:?int,title:string,body:string,status:string,statusLabel:string,priority:string,priorityLabel:string,channel:string,channelLabel:string,customerName:string,segmentLabel:string,loyaltyTier:string,nextTouch:string,loyaltyMove:string,handledBy:?string,assignedTo:?string,assignedUserId:?string,locationName:string,locationId:?string,assignmentGroup:string,assignmentGroupLabel:string,nextActionAt:?string,profileUrl:string,slaLabel:string,slaColor:string,replyCount:int,responseDueAt:?string}> */
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

        $count = app(CustomerInboxReadModel::class)->badgeCount($tenant);

        return $count > 0 ? (string) $count : null;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $this->tenantName = (string) $tenant->name;
        $reader = app(CustomerInboxReadModel::class);
        $this->teamOptions = $reader->resolveTeamOptions($tenant);
        $this->locationOptions = $reader->resolveLocationOptions($tenant);
        $this->assignmentGroupOptions = app(CustomerConversationRouting::class)->options();
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

    public function updatedSelectedAssignmentGroup(): void
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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $updated = app(TakeCustomerInboxConversationHandler::class)->handle(
            new TakeCustomerInboxConversationCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
        );

        if (! $updated instanceof CustomerNote) {
            return;
        }

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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $updated = app(ResolveCustomerInboxConversationHandler::class)->handle(
            new ResolveCustomerInboxConversationCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
        );

        if (! $updated instanceof CustomerNote) {
            return;
        }

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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $normalizedUserId = filled($userId) ? (string) $userId : null;
        $updated = app(ReassignCustomerInboxConversationHandler::class)->handle(
            new ReassignCustomerInboxConversationCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                ownerUserId: $normalizedUserId,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
        );

        if (! $updated instanceof CustomerNote) {
            return;
        }

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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $normalizedLocationId = filled($locationId) ? (string) $locationId : null;
        $updated = app(MoveCustomerInboxConversationLocationHandler::class)->handle(
            new MoveCustomerInboxConversationLocationCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                locationId: $normalizedLocationId,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
        );

        if (! $updated instanceof CustomerNote) {
            return;
        }

        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Sede actualizada')
            ->success()
            ->body($normalizedLocationId !== null ? 'La conversacion ya esta ligada a otra sede.' : 'La conversacion quedo sin sede asignada.')
            ->send();
    }

    public function routeGroup(int $noteId, ?string $group = null): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $normalizedGroup = filled($group) ? (string) $group : null;
        $updated = app(RouteCustomerInboxConversationGroupHandler::class)->handle(
            new RouteCustomerInboxConversationGroupCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                group: $normalizedGroup,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
        );

        if (! $updated instanceof CustomerNote) {
            return;
        }

        $this->selectedConversationId = $noteId;
        $this->refreshQueue();

        Notification::make()
            ->title('Equipo actualizado')
            ->success()
            ->body($normalizedGroup !== null ? 'La conversacion ya cambio de equipo operativo.' : 'La conversacion quedo sin equipo asignado.')
            ->send();
    }

    public function sendSuggestedReply(int $noteId, string $templateKey): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        try {
            $touchpoint = app(SendCustomerInboxTemplateHandler::class)->handle(
                new SendCustomerInboxTemplateCommand(
                    tenantId: (string) $tenant->getKey(),
                    noteId: $noteId,
                    templateKey: $templateKey,
                    actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
                ),
            );
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('No se pudo enviar')
                ->danger()
                ->body($exception->getMessage())
                ->send();

            return;
        }

        if ($touchpoint === null) {
            Notification::make()
                ->title('No se pudo enviar')
                ->danger()
                ->body('La conversacion no tiene un cliente valido ligado al CRM.')
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
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return;
        }

        $entry = app(AddCustomerInboxInternalNoteHandler::class)->handle(
            new AddCustomerInboxInternalNoteCommand(
                tenantId: (string) $tenant->getKey(),
                noteId: $noteId,
                body: $this->draftInternalNote,
                actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
            ),
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
            $this->assignmentGroupOverview = [];
            $this->ownerOverview = [];
            $this->locationOverview = [];

            return;
        }
        $reader = app(CustomerInboxReadModel::class);
        $result = $reader->build(
            tenant: $tenant,
            filters: [
                'scope' => $this->selectedScope,
                'queueLane' => $this->selectedQueueLane,
                'channel' => $this->selectedChannel,
                'ownerId' => $this->selectedOwnerId,
                'locationId' => $this->selectedLocationId,
                'assignmentGroup' => $this->selectedAssignmentGroup,
                'slaState' => $this->selectedSlaState,
            ],
            assignmentGroupOptions: $this->assignmentGroupOptions,
            selectedConversationId: $this->selectedConversationId,
            actorUserId: auth()->id() !== null ? (string) auth()->id() : null,
        );

        $this->openCount = $result['openCount'];
        $this->inProgressCount = $result['inProgressCount'];
        $this->waitingCustomerCount = $result['waitingCustomerCount'];
        $this->resolvedTodayCount = $result['resolvedTodayCount'];
        $this->urgentCount = $result['urgentCount'];
        $this->breachedCount = $result['breachedCount'];
        $this->myQueueCount = $result['myQueueCount'];
        $this->dueTodayCount = $result['dueTodayCount'];
        $this->unassignedCount = $result['unassignedCount'];
        $this->channelOverview = $result['channelOverview'];
        $this->assignmentGroupOverview = $result['assignmentGroupOverview'];
        $this->ownerOverview = $result['ownerOverview'];
        $this->locationOverview = $result['locationOverview'];
        $this->queue = $result['queue'];
        $this->selectedConversationId = $result['selectedConversationId'];
        $this->selectedConversation = $result['selectedConversation'];
    }

    private function refreshSelectedConversation(): void
    {
        $tenant = Filament::getTenant();

        if ($this->selectedConversationId === null && $this->queue !== []) {
            $this->selectedConversationId = (int) $this->queue[0]['id'];
        }

        if (! $tenant instanceof Tenant || $this->selectedConversationId === null) {
            $this->selectedConversation = [];

            return;
        }

        $reader = app(CustomerInboxReadModel::class);
        $note = $reader->findTenantNote($tenant, $this->selectedConversationId);

        if (! $note instanceof CustomerNote) {
            $this->selectedConversation = [];
            $this->selectedConversationId = $this->queue !== [] ? (int) $this->queue[0]['id'] : null;

            if ($this->selectedConversationId !== null) {
                $this->refreshSelectedConversation();
            }

            return;
        }

        $this->selectedConversation = $reader->buildConversationWorkspace($tenant, $note);
    }
}
