<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domain\TenantGovernance\Services\CustomerSegmentPlaybook;
use App\Filament\App\Resources\CustomerProfileResource;
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

    public int $resolvedTodayCount = 0;

    public int $urgentCount = 0;

    /** @var list<array{id:int,title:string,body:string,status:string,statusLabel:string,priority:string,priorityLabel:string,channel:string,channelLabel:string,customerName:string,segmentLabel:string,loyaltyTier:string,nextTouch:string,loyaltyMove:string,handledBy:?string,nextActionAt:?string,profileUrl:string}> */
    public array $queue = [];

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
        $this->refreshQueue();
    }

    public function take(int $noteId): void
    {
        $note = $this->findTenantNote($noteId);

        if (! $note instanceof CustomerNote) {
            return;
        }

        $note->forceFill([
            'status' => 'in_progress',
            'handled_by_user_id' => auth()->id(),
        ])->save();

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

        $note->forceFill([
            'status' => 'resolved',
            'handled_by_user_id' => auth()->id(),
            'handled_at' => now(),
            'requires_follow_up' => false,
            'next_action_at' => null,
        ])->save();

        $this->refreshQueue();

        Notification::make()
            ->title('Caso resuelto')
            ->success()
            ->body('La respuesta ya salio de la cola activa.')
            ->send();
    }

    private function refreshQueue(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            $this->queue = [];

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

        $playbooks = app(CustomerSegmentPlaybook::class);

        $this->queue = (clone $baseQuery)
            ->with([
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes(),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('next_action_at')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (CustomerNote $note) use ($playbooks, $tenant): array {
                $profile = $note->customerProfile()->withoutGlobalScopes()->first();
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
                    'nextActionAt' => $note->next_action_at?->diffForHumans(),
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
            ->find($noteId);
    }
}
