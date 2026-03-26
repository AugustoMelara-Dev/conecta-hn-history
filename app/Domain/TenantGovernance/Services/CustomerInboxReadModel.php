<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Filament\App\Resources\CustomerProfileResource;
use App\Models\CustomerConversation;
use App\Models\CustomerJourneyRun;
use App\Models\CustomerNote;
use App\Models\CustomerProfile;
use App\Models\CustomerTask;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

final class CustomerInboxReadModel
{
    public function __construct(
        private readonly CustomerSegmentPlaybook $playbook,
        private readonly CustomerConversationThreadBuilder $threadBuilder,
        private readonly CustomerConversationTemplateManager $templateManager,
    ) {}

    /**
     * @param  array{scope:string,queueLane:string,channel:string,ownerId:?string,locationId:?string,assignmentGroup:string,slaState:string}  $filters
     * @param  array<string, string>  $assignmentGroupOptions
     * @return array<string, mixed>
     */
    public function build(
        Tenant $tenant,
        array $filters,
        array $assignmentGroupOptions,
        ?int $selectedConversationId,
        ?string $actorUserId,
    ): array {
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

        $queue = $this->applyQueueFilters(clone $baseQuery, $filters, $actorUserId)
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
            ->orderByRaw($this->priorityOrderSql())
            ->orderBy('response_due_at')
            ->orderBy('next_action_at')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (CustomerNote $note): array => $this->mapQueueNote($note, $tenant))
            ->values()
            ->all();

        $selectedId = $selectedConversationId;
        if ($selectedId === null && $queue !== []) {
            $selectedId = (int) $queue[0]['id'];
        }

        $selectedConversation = [];
        if ($selectedId !== null) {
            $note = $this->findTenantNote($tenant, $selectedId);
            if ($note instanceof CustomerNote) {
                $selectedConversation = $this->buildConversationWorkspace($tenant, $note);
            } elseif ($queue !== []) {
                $selectedId = (int) $queue[0]['id'];
                $fallback = $this->findTenantNote($tenant, $selectedId);
                $selectedConversation = $fallback instanceof CustomerNote ? $this->buildConversationWorkspace($tenant, $fallback) : [];
            } else {
                $selectedId = null;
            }
        }

        return [
            'openCount' => (clone $conversationBase)->where('status', 'open')->count(),
            'inProgressCount' => (clone $conversationBase)->where('status', 'in_progress')->count(),
            'waitingCustomerCount' => (clone $conversationBase)->where('status', 'waiting_customer')->count(),
            'resolvedTodayCount' => (clone $conversationBase)->where('status', 'closed')->where('closed_at', '>=', now()->startOfDay())->count(),
            'urgentCount' => (clone $conversationBase)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->where('priority', 'urgent')->count(),
            'breachedCount' => (clone $conversationBase)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->where(function ($query): void {
                $query->whereNotNull('response_due_at')->where('response_due_at', '<', now());
            })->count(),
            'myQueueCount' => $actorUserId !== null
                ? (clone $conversationBase)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->where('owner_user_id', $actorUserId)->count()
                : 0,
            'dueTodayCount' => (clone $conversationBase)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->whereNotNull('response_due_at')->whereBetween('response_due_at', [now(), now()->endOfDay()])->count(),
            'unassignedCount' => (clone $conversationBase)->whereIn('status', ['open', 'in_progress', 'waiting_customer'])->whereNull('owner_user_id')->count(),
            'channelOverview' => $this->buildConversationOverviewRows($activeConversations, 'source_channel', [
                'whatsapp' => 'WhatsApp',
                'email' => 'Email',
                'crm' => 'CRM',
            ]),
            'assignmentGroupOverview' => $this->buildConversationOverviewRows($activeConversations, 'assignment_group', $assignmentGroupOptions),
            'ownerOverview' => $this->buildConversationOverviewRows($activeConversations, 'owner_user_id'),
            'locationOverview' => $this->buildConversationOverviewRows($activeConversations, 'location_id'),
            'queue' => $queue,
            'selectedConversationId' => $selectedId,
            'selectedConversation' => $selectedConversation,
        ];
    }

    private function priorityOrderSql(): string
    {
        return match (CustomerNote::query()->withoutGlobalScopes()->getConnection()->getDriverName()) {
            'sqlite' => "case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 when 'low' then 4 else 5 end",
            default => "field(priority, 'urgent', 'high', 'normal', 'low')",
        };
    }

    /**
     * @return array<string, string>
     */
    public function resolveTeamOptions(Tenant $tenant): array
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
    public function resolveLocationOptions(Tenant $tenant): array
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

    public function badgeCount(Tenant $tenant): int
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->where('requires_follow_up', true)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();
    }

    private function mapQueueNote(CustomerNote $note, Tenant $tenant): array
    {
        $profile = $note->customerProfile;
        $conversation = $note->conversation;
        $playbook = $profile instanceof CustomerProfile ? $this->playbook->forProfile($profile) : null;
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
            'assignmentGroup' => (string) ($conversation?->assignment_group ?? data_get($note->metadata, 'assignment_group', '')),
            'assignmentGroupLabel' => $conversation?->assignment_group_label
                ?? (string) data_get($note->metadata, 'assignment_group_label', 'Sin equipo'),
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
            'journeyBranchLabel' => (string) data_get($journeyRun?->metadata, 'current_branch_label', 'Ruta base'),
            'journeySequenceLabel' => (string) data_get($journeyRun?->metadata, 'current_sequence_label', 'Secuencia principal'),
            'nextSequenceLabel' => (string) data_get($journeyRun?->metadata, 'next_sequence_label', 'Sin siguiente secuencia'),
            'nextTriggerLabel' => $journeyRun?->step_due_at?->diffForHumans(),
            'profileUrl' => $profile instanceof CustomerProfile
                ? CustomerProfileResource::getUrl('edit', ['record' => $profile, 'tenant' => $tenant->slug])
                : '#',
        ];
    }

    public function buildConversationWorkspace(Tenant $tenant, CustomerNote $note): array
    {
        $profile = $note->customerProfile;
        $conversation = $note->conversation;

        if (! $profile instanceof CustomerProfile) {
            return [];
        }

        $playbook = $this->playbook->forProfile($profile);
        $thread = $this->threadBuilder->build($profile, $note, max(8, (int) config('customer-conversations.defaults.thread_limit', 16)));
        $journey = $this->templateManager->forConversation($profile, $note);
        $task = $note->task()->withoutGlobalScopes()->with(['assignedUser', 'location'])->first();
        $journeyRun = $conversation?->journeyRun;
        $journeyRunSummary = $journeyRun instanceof CustomerJourneyRun ? [
            'statusLabel' => $journeyRun->status_label,
            'sequenceStateLabel' => $journeyRun->sequence_state_label,
            'dueStateLabel' => $journeyRun->due_state_label,
            'currentStepIndex' => (int) $journeyRun->current_step_index,
            'nextActionAt' => $journeyRun->next_action_at?->diffForHumans(),
            'stepDueAt' => $journeyRun->step_due_at?->diffForHumans(),
            'currentStep' => data_get($journeyRun->metadata, 'current_step.title'),
            'currentStepCopy' => data_get($journeyRun->metadata, 'current_step.copy'),
            'currentLane' => data_get($journeyRun->metadata, 'lane'),
            'currentBranchLabel' => data_get($journeyRun->metadata, 'current_branch_label', 'Ruta base'),
            'currentSequenceLabel' => data_get($journeyRun->metadata, 'current_sequence_label', 'Secuencia principal'),
            'nextStep' => data_get($journeyRun->metadata, 'next_step.title'),
            'nextBranchLabel' => data_get($journeyRun->metadata, 'next_branch_label'),
            'nextSequenceLabel' => data_get($journeyRun->metadata, 'next_sequence_label'),
            'nextTemplateKey' => $journeyRun->next_template_key,
            'recommendedTemplateKey' => data_get($journeyRun->metadata, 'recommended_template_key'),
            'nextTriggerAt' => $journeyRun->step_due_at?->diffForHumans(),
            'lastBusinessSignal' => $this->formatJourneySignalLabel((string) data_get($journeyRun->metadata, 'last_business_signal', '')),
            'lastBusinessSignalAt' => $this->formatJourneySignalAt(data_get($journeyRun->metadata, 'last_business_signal_at')),
        ] : (($journey['journey'] !== [] || $journey['templates'] !== []) ? [
            'statusLabel' => 'Listo',
            'sequenceStateLabel' => 'Sincronizando',
            'dueStateLabel' => $conversation?->sla_label ?? $note->sla_label,
            'currentStepIndex' => 1,
            'nextActionAt' => ($conversation?->next_action_at ?? $note->next_action_at)?->diffForHumans(),
            'stepDueAt' => ($conversation?->response_due_at ?? $note->response_due_at)?->diffForHumans(),
            'currentStep' => $journey['current_step']['title'] ?? 'Sin paso activo',
            'currentStepCopy' => $journey['current_step']['copy'] ?? 'La ruta comercial ya esta lista para moverse desde este hilo.',
            'currentLane' => 'follow_up',
            'currentBranchLabel' => 'Ruta base',
            'currentSequenceLabel' => 'Secuencia principal',
            'nextStep' => $journey['next_step']['title'] ?? null,
            'nextBranchLabel' => 'Ruta base',
            'nextSequenceLabel' => 'Secuencia principal',
            'nextTemplateKey' => null,
            'recommendedTemplateKey' => $journey['templates'][0]['key'] ?? null,
            'nextTriggerAt' => ($conversation?->next_action_at ?? $note->next_action_at)?->diffForHumans(),
            'lastBusinessSignal' => null,
            'lastBusinessSignalAt' => null,
        ] : null);

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
            'assignmentGroup' => (string) ($conversation?->assignment_group ?? data_get($note->metadata, 'assignment_group', '')),
            'assignmentGroupLabel' => $conversation?->assignment_group_label
                ?? (string) data_get($note->metadata, 'assignment_group_label', 'Sin equipo'),
            'ownerLabel' => $conversation?->owner?->name ?? $note->assignedUser?->name ?? $profile->assigned_owner_label,
            'locationLabel' => $conversation?->location?->name ?? $note->location?->name ?? $profile->location_label,
            'lastActionAt' => ($conversation?->next_action_at ?? $note->next_action_at)?->diffForHumans(),
            'responseDueAt' => ($conversation?->response_due_at ?? $note->response_due_at)?->diffForHumans(),
            'replyCount' => max(1, (int) ($conversation?->reply_count ?? $note->reply_count)),
            'profileUrl' => CustomerProfileResource::getUrl('edit', ['record' => $profile, 'tenant' => $tenant->slug]),
            'threadSummary' => $thread['summary'],
            'threadItems' => $thread['items'],
            'internalNotes' => $thread['internal_notes'],
            'journey' => $journey['journey'],
            'journeyRun' => $journeyRunSummary,
            'currentStep' => $journey['current_step'],
            'nextStep' => $journey['next_step'],
            'templates' => $journey['templates'],
            'task' => $task instanceof CustomerTask ? [
                'title' => (string) $task->title,
                'body' => (string) $task->body,
                'statusLabel' => $task->status_label,
                'priorityLabel' => $task->priority_label,
                'slaLabel' => $task->sla_label,
                'assignmentGroupLabel' => $task->assignment_group_label,
                'ownerLabel' => $task->assignedUser?->name ?? $profile->assigned_owner_label,
                'locationLabel' => $task->location?->name ?? $profile->location_label,
                'dueAt' => $task->due_at?->diffForHumans(),
                'slaDueAt' => $task->sla_due_at?->diffForHumans(),
                'rescueReason' => (string) data_get($task->metadata, 'rescue_reason', ''),
            ] : null,
        ];
    }

    private function buildConversationOverviewRows($conversations, string $groupBy, array $labels = []): array
    {
        return $conversations
            ->groupBy(fn (CustomerConversation $conversation): string => (string) ($conversation->{$groupBy} ?? ''))
            ->map(function ($items, string $key) use ($groupBy, $labels): array {
                $name = match ($groupBy) {
                    'source_channel' => $labels[$key] ?? $this->formatChannelLabel($key),
                    'assignment_group' => $labels[$key] ?? ($key !== '' ? str($key)->replace('_', ' ')->title()->value() : 'Sin equipo'),
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

    public function findTenantNote(Tenant $tenant, int $noteId): ?CustomerNote
    {
        return CustomerNote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->with([
                'assignedUser' => fn ($query) => $query->withoutGlobalScopes(),
                'handledBy' => fn ($query) => $query->withoutGlobalScopes(),
                'location' => fn ($query) => $query->withoutGlobalScopes(),
                'customerProfile' => fn ($query) => $query->withoutGlobalScopes()->with(['assignedUser', 'location', 'tenant']),
                'conversation' => fn ($query) => $query->withoutGlobalScopes()->with([
                    'owner' => fn ($owner) => $owner->withoutGlobalScopes(),
                    'location' => fn ($location) => $location->withoutGlobalScopes(),
                    'journeyRun',
                ]),
            ])
            ->find($noteId);
    }

    private function applyQueueFilters($query, array $filters, ?string $actorUserId)
    {
        if (($filters['scope'] ?? 'all') === 'mine' && $actorUserId !== null) {
            $query->where(function ($mine) use ($actorUserId): void {
                $mine->where('assigned_user_id', $actorUserId)
                    ->orWhereHas('conversation', function ($conversation) use ($actorUserId): void {
                        $conversation->withoutGlobalScopes()->where('owner_user_id', $actorUserId);
                    });
            });
        } elseif (($filters['scope'] ?? 'all') === 'unassigned') {
            $query->where(function ($unassigned): void {
                $unassigned->whereNull('assigned_user_id')
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()->whereNull('owner_user_id');
                    });
            });
        }

        if (filled($filters['ownerId'] ?? null)) {
            $ownerId = (string) $filters['ownerId'];
            $query->where(function ($owner) use ($ownerId): void {
                $owner->where('assigned_user_id', $ownerId)
                    ->orWhereHas('conversation', function ($conversation) use ($ownerId): void {
                        $conversation->withoutGlobalScopes()->where('owner_user_id', $ownerId);
                    });
            });
        }

        if (filled($filters['locationId'] ?? null)) {
            $locationId = (string) $filters['locationId'];
            $query->where(function ($location) use ($locationId): void {
                $location->where('location_id', $locationId)
                    ->orWhereHas('conversation', function ($conversation) use ($locationId): void {
                        $conversation->withoutGlobalScopes()->where('location_id', $locationId);
                    });
            });
        }

        if (($filters['assignmentGroup'] ?? 'all') !== 'all') {
            $group = (string) $filters['assignmentGroup'];
            $query->where(function ($assignment) use ($group): void {
                $assignment->where('metadata->assignment_group', $group)
                    ->orWhereHas('conversation', function ($conversation) use ($group): void {
                        $conversation->withoutGlobalScopes()->where('assignment_group', $group);
                    });
            });
        }

        if (($filters['channel'] ?? 'all') !== 'all') {
            $channel = (string) $filters['channel'];
            $query->where(function ($queryChannel) use ($channel): void {
                $queryChannel->where('source_channel', $channel)
                    ->orWhereHas('conversation', function ($conversation) use ($channel): void {
                        $conversation->withoutGlobalScopes()->where('source_channel', $channel);
                    });
            });
        }

        $queueLane = (string) ($filters['queueLane'] ?? 'all');
        if ($queueLane === 'waiting_customer') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->where('status', 'waiting_customer');
            });
        } elseif ($queueLane === 'stalled') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->whereHas('journeyRun', function ($journey): void {
                    $journey->withoutGlobalScopes()->where('sequence_state', 'stalled');
                });
            });
        } elseif ($queueLane === 'closing') {
            $query->whereHas('conversation', function ($conversation): void {
                $conversation->withoutGlobalScopes()->whereHas('journeyRun', function ($journey): void {
                    $journey->withoutGlobalScopes()->where('current_step_key', 'like', '%close%');
                });
            });
        } elseif ($queueLane === 'active') {
            $query->where(function ($active): void {
                $active->whereIn('status', ['open', 'in_progress'])
                    ->orWhereHas('conversation', function ($conversation): void {
                        $conversation->withoutGlobalScopes()->whereIn('status', ['open', 'in_progress']);
                    });
            });
        }

        $slaState = (string) ($filters['slaState'] ?? 'all');
        if ($slaState === 'breached') {
            $query->where(function ($breached): void {
                $breached->where(function ($noteBreached): void {
                    $noteBreached->whereNotNull('response_due_at')->where('response_due_at', '<', now());
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()->whereNotNull('response_due_at')->where('response_due_at', '<', now());
                });
            });
        } elseif ($slaState === 'today') {
            $query->where(function ($today): void {
                $today->where(function ($noteToday): void {
                    $noteToday->whereNotNull('response_due_at')->whereBetween('response_due_at', [now(), now()->endOfDay()]);
                })->orWhereHas('conversation', function ($conversation): void {
                    $conversation->withoutGlobalScopes()->whereNotNull('response_due_at')->whereBetween('response_due_at', [now(), now()->endOfDay()]);
                });
            });
        } elseif ($slaState === 'healthy') {
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

    private function formatChannelLabel(string $channel): string
    {
        return match ($channel) {
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'crm' => 'CRM',
            default => str($channel)->replace('_', ' ')->replace('-', ' ')->title()->value(),
        };
    }

    private function formatJourneySignalLabel(string $signal): ?string
    {
        if ($signal === '') {
            return null;
        }

        return str($signal)->replace('_', ' ')->title()->value();
    }

    private function formatJourneySignalAt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return null;
        }
    }
}
