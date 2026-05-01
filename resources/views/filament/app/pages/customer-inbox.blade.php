<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-stack">
                        <p class="biz-kicker">Bandeja de respuestas</p>
                        <h1 class="biz-title">{{ $tenantName }}</h1>
                        <p class="biz-heading">Conversaciones que necesitan seguimiento humano</p>
                        <p class="biz-copy">
                            Trabaja replies, ownership, segmento y continuidad desde una sola cola. La meta no es solo responder: es cerrar el siguiente paso correcto.
                        </p>
                    </div>

                    <div class="biz-metrics">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Abiertas</p>
                            <p class="biz-stat-value">{{ $openCount }}</p>
                            <p class="biz-stat-copy">Respuestas esperando primer movimiento.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">En curso</p>
                            <p class="biz-stat-value">{{ $inProgressCount }}</p>
                            <p class="biz-stat-copy">Casos ya tomados por alguien del equipo.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Esperando cliente</p>
                            <p class="biz-stat-value">{{ $waitingCustomerCount }}</p>
                            <p class="biz-stat-copy">Conversaciones en follow-up que ya requieren nuevo rescate o cierre.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Mi cola</p>
                            <p class="biz-stat-value">{{ $myQueueCount }}</p>
                            <p class="biz-stat-copy">Conversaciones que ya tienen owner asignado a tu usuario.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">SLA vencido</p>
                            <p class="biz-stat-value">{{ $breachedCount }}</p>
                            <p class="biz-stat-copy">Conversaciones fuera de tiempo que conviene rescatar ya.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Vence hoy</p>
                            <p class="biz-stat-value">{{ $dueTodayCount }}</p>
                            <p class="biz-stat-copy">Casos que conviene mover antes de cerrar el día.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Sin owner</p>
                            <p class="biz-stat-value">{{ $unassignedCount }}</p>
                            <p class="biz-stat-copy">Conversaciones que todavía no tienen responsable claro.</p>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Regla operativa</p>
                            <h2 class="biz-heading">Responder con contexto, mover el siguiente paso, dejar huella clara</h2>
                            <p class="biz-muted">
                                Usa segmento, fidelidad, owner, sede y SLA para decidir el mejor toque y no solo el más rápido.
                            </p>
                        </div>

                        <div class="biz-actions">
                            <div class="biz-action">
                                <p class="biz-action-title">Prospectos y nuevos</p>
                                <p class="biz-action-copy">Responde hoy y deja una salida clara a compra, llamada o agenda.</p>
                            </div>
                            <div class="biz-action">
                                <p class="biz-action-title">Recurrentes y VIP</p>
                                <p class="biz-action-copy">Usa bundle, reward o trato prioritario para mover continuidad real.</p>
                            </div>
                            <div class="biz-action">
                                <p class="biz-action-title">Reactivación</p>
                                <p class="biz-action-copy">Baja fricción y deja un motivo concreto para volver esta semana.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split biz-split-end">
                    <div>
                        <p class="biz-kicker">Mesa operativa</p>
                        <h2 class="biz-heading">Cola, hilo y secuencia activa</h2>
                        <p class="biz-muted">
                            Filtra por owner, sede, canal y lane del journey; luego trabaja la conversación desde el hilo, no solo desde la tarjeta.
                        </p>
                    </div>

                    <div class="biz-grid-two biz-grid-filters">
                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Vista</span>
                            <select wire:model.live="selectedScope" class="fi-input">
                                <option value="all">Toda la cola</option>
                                <option value="mine">Solo mi cola</option>
                                <option value="unassigned">Sin owner</option>
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Lane</span>
                            <select wire:model.live="selectedQueueLane" class="fi-input">
                                <option value="all">Todo el journey</option>
                                <option value="active">Primer toque</option>
                                <option value="waiting_customer">Esperando cliente</option>
                                <option value="stalled">Requiere rescate</option>
                                <option value="closing">Cierre</option>
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Canal</span>
                            <select wire:model.live="selectedChannel" class="fi-input">
                                <option value="all">Todos</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="crm">CRM</option>
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">SLA</span>
                            <select wire:model.live="selectedSlaState" class="fi-input">
                                <option value="all">Todo</option>
                                <option value="breached">Vencido</option>
                                <option value="today">Vence hoy</option>
                                <option value="healthy">En tiempo</option>
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Owner</span>
                            <select wire:model.live="selectedOwnerId" class="fi-input">
                                <option value="">Todos</option>
                                @foreach ($teamOptions as $userId => $label)
                                    <option value="{{ $userId }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Sede</span>
                            <select wire:model.live="selectedLocationId" class="fi-input">
                                <option value="">Todas</option>
                                @foreach ($locationOptions as $locationId => $label)
                                    <option value="{{ $locationId }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="biz-stack biz-stack-xxs">
                            <span class="biz-muted">Equipo</span>
                            <select wire:model.live="selectedAssignmentGroup" class="fi-input">
                                <option value="all">Todos</option>
                                @foreach ($assignmentGroupOptions as $groupKey => $label)
                                    <option value="{{ $groupKey }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="biz-grid-two biz-grid-four">
                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Omnicanal</p>
                                <h3 class="biz-heading">Carga por canal</h3>
                            </div>

                            <div class="biz-actions">
                                @forelse ($channelOverview as $item)
                                    <div class="biz-action">
                                        <p class="biz-action-title">{{ $item['label'] }}</p>
                                        <p class="biz-muted">
                                            <strong>Total:</strong> {{ $item['total'] }}
                                            | <strong>Esperando:</strong> {{ $item['waiting'] }}
                                            | <strong>Vencidas:</strong> {{ $item['breached'] }}
                                        </p>
                                    </div>
                                @empty
                                    @include('filament.app.components.table-empty-state', [
                                        'badge' => 'Canales',
                                        'heading' => 'Todavía no hay lectura por canal',
                                        'description' => 'Cuando entren conversaciones por WhatsApp, email o CRM, aquí verás carga, espera y vencimientos por canal.',
                                    ])
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Ownership</p>
                                <h3 class="biz-heading">Carga por owner</h3>
                            </div>

                            <div class="biz-actions">
                                @forelse ($ownerOverview as $item)
                                    <div class="biz-action">
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-muted">
                                            <strong>Total:</strong> {{ $item['total'] }}
                                            | <strong>Esperando:</strong> {{ $item['waiting'] }}
                                            | <strong>Vencidas:</strong> {{ $item['breached'] }}
                                        </p>
                                    </div>
                                @empty
                                    @include('filament.app.components.table-empty-state', [
                                        'badge' => 'Owners',
                                        'heading' => 'Todavía no hay carga por owner',
                                        'description' => 'Cuando el equipo tome conversaciones, esta vista mostrará quién tiene cartera activa y dónde hay vencimientos.',
                                    ])
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Equipos</p>
                                <h3 class="biz-heading">Carga por equipo</h3>
                            </div>

                            <div class="biz-actions">
                                @forelse ($assignmentGroupOverview as $item)
                                    <div class="biz-action">
                                        <p class="biz-action-title">{{ $item['label'] }}</p>
                                        <p class="biz-muted">
                                            <strong>Total:</strong> {{ $item['total'] }}
                                            | <strong>Esperando:</strong> {{ $item['waiting'] }}
                                            | <strong>Vencidas:</strong> {{ $item['breached'] }}
                                        </p>
                                    </div>
                                @empty
                                    @include('filament.app.components.table-empty-state', [
                                        'badge' => 'Equipos',
                                        'heading' => 'Todavía no hay carga por equipo',
                                        'description' => 'Cuando agenda, ventas o soporte reciban casos, aquí aparecerá la distribución operativa.',
                                    ])
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Operación por sede</p>
                                <h3 class="biz-heading">Carga por ubicación</h3>
                            </div>

                            <div class="biz-actions">
                                @forelse ($locationOverview as $item)
                                    <div class="biz-action">
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-muted">
                                            <strong>Total:</strong> {{ $item['total'] }}
                                            | <strong>Esperando:</strong> {{ $item['waiting'] }}
                                            | <strong>Vencidas:</strong> {{ $item['breached'] }}
                                        </p>
                                    </div>
                                @empty
                                    @include('filament.app.components.table-empty-state', [
                                        'badge' => 'Sedes',
                                        'heading' => 'Todavía no hay operación por sede',
                                        'description' => 'Cuando una ubicación empiece a concentrar conversaciones, esta lectura mostrará carga y vencimientos por sede.',
                                    ])
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="biz-grid-two biz-grid-start">
                    <div class="biz-stack">
                        @if ($queue === [])
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Cola',
                                'heading' => 'Todo al día',
                                'description' => 'No hay respuestas pendientes con este filtro. Cambia la vista, canal, owner o SLA para revisar otra parte de la cola.',
                            ])
                        @else
                            <div class="biz-actions">
                                @foreach ($queue as $item)
                                    <div class="biz-action {{ $selectedConversationId === $item['id'] ? 'is-selected' : '' }}">
                                        <div class="biz-stack biz-stack-sm">
                                            <div class="biz-split biz-split-start">
                                                <div class="biz-stack biz-stack-xs biz-flex-1">
                                                    <div class="biz-button-row">
                                                        <span class="biz-badge biz-badge-neutral">{{ $item['channelLabel'] }}</span>
                                                        <span class="biz-badge {{ $item['priority'] === 'urgent' ? 'biz-badge-danger' : ($item['priority'] === 'high' ? 'biz-badge-warning' : 'biz-badge-neutral') }}">{{ $item['priorityLabel'] }}</span>
                                                        <span class="biz-badge {{ $item['status'] === 'resolved' ? 'biz-badge-success' : ($item['status'] === 'in_progress' ? 'biz-badge-info' : ($item['status'] === 'pending_customer' ? 'biz-badge-neutral' : 'biz-badge-warning')) }}">{{ $item['statusLabel'] }}</span>
                                                        <span class="biz-badge {{ $item['slaColor'] === 'danger' ? 'biz-badge-danger' : ($item['slaColor'] === 'success' ? 'biz-badge-success' : 'biz-badge-neutral') }}">{{ $item['slaLabel'] }}</span>
                                                    </div>

                                                    <div>
                                                        <p class="biz-action-title">{{ $item['customerName'] }}</p>
                                                        <p class="biz-action-copy">{{ $item['body'] }}</p>
                                                    </div>

                                                    <div class="biz-stack biz-stack-xxs">
                                                        <p class="biz-muted"><strong>Segmento:</strong> {{ $item['segmentLabel'] }} | <strong>Fidelidad:</strong> {{ $item['loyaltyTier'] }}</p>
                                                        <p class="biz-muted"><strong>Siguiente toque:</strong> {{ $item['nextTouch'] }}</p>
                                                        <p class="biz-muted"><strong>Movimiento loyalty:</strong> {{ $item['loyaltyMove'] }}</p>
                                                        <p class="biz-muted"><strong>Owner:</strong> {{ $item['assignedTo'] ?? 'Sin asignar' }} | <strong>Sede:</strong> {{ $item['locationName'] }} | <strong>Equipo:</strong> {{ $item['assignmentGroupLabel'] }}</p>
                                                        <p class="biz-muted"><strong>Journey:</strong> {{ $item['journeyStep'] ?? 'Sin paso activo' }} | <strong>Ruta:</strong> {{ $item['journeyBranchLabel'] }} | <strong>Secuencia:</strong> {{ $item['journeySequenceLabel'] }}</p>
                                                        <p class="biz-muted"><strong>Lane:</strong> {{ str($item['queueLane'])->replace('_', ' ')->title() }} | <strong>Estado secuencia:</strong> {{ $item['sequenceStateLabel'] }} | <strong>Disparo siguiente:</strong> {{ $item['nextTriggerLabel'] ?? 'Sin trigger' }}</p>
                                                        <p class="biz-muted"><strong>Plantilla recomendada:</strong> {{ $item['recommendedTemplateLabel'] }} | <strong>Lectura de due:</strong> {{ $item['dueStateLabel'] }}</p>
                                                        <p class="biz-muted">
                                                            <strong>Operador actual:</strong> {{ $item['handledBy'] ?? 'Sin tomar' }}
                                                            @if ($item['nextActionAt'])
                                                                | <strong>Próxima acción:</strong> {{ $item['nextActionAt'] }}
                                                            @endif
                                                            @if ($item['responseDueAt'])
                                                                | <strong>SLA:</strong> {{ $item['responseDueAt'] }}
                                                            @endif
                                                            | <strong>Mensajes:</strong> {{ $item['replyCount'] }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="biz-button-row biz-row-end">
                                                    <x-filament::button
                                                        color="{{ $selectedConversationId === $item['id'] ? 'info' : 'gray' }}"
                                                        wire:click="selectConversation({{ $item['id'] }})"
                                                        aria-label="Ver hilo de {{ $item['customerName'] }}"
                                                        aria-pressed="{{ $selectedConversationId === $item['id'] ? 'true' : 'false' }}"
                                                    >
                                                        Ver hilo
                                                    </x-filament::button>
                                                    <a href="{{ $item['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
                                                </div>
                                            </div>

                                            <div class="biz-grid-two biz-grid-three-wide">
                                                <label class="biz-stack biz-stack-xxs">
                                                    <span class="biz-muted">Reasignar owner</span>
                                                    <select wire:model.live="pendingOwnerIds.{{ $item['id'] }}" class="fi-input">
                                                        <option value="">Sin owner</option>
                                                        @foreach ($teamOptions as $userId => $label)
                                                            <option value="{{ $userId }}" @selected($item['assignedUserId'] === (string) $userId)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-filament::button
                                                        size="sm"
                                                        color="gray"
                                                        wire:click="applyOwnerChange({{ $item['id'] }})"
                                                        wire:confirm="¿Confirmas cambiar el owner de este caso?"
                                                        aria-label="Aplicar cambio de owner para {{ $item['customerName'] }}"
                                                    >
                                                        Aplicar
                                                    </x-filament::button>
                                                </label>

                                                <label class="biz-stack biz-stack-xxs">
                                                    <span class="biz-muted">Mover equipo</span>
                                                    <select wire:model.live="pendingGroupIds.{{ $item['id'] }}" class="fi-input">
                                                        <option value="">Sin equipo</option>
                                                        @foreach ($assignmentGroupOptions as $groupKey => $label)
                                                            <option value="{{ $groupKey }}" @selected($item['assignmentGroup'] === (string) $groupKey)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-filament::button
                                                        size="sm"
                                                        color="gray"
                                                        wire:click="applyGroupChange({{ $item['id'] }})"
                                                        wire:confirm="¿Confirmas mover este caso a otro equipo?"
                                                        aria-label="Aplicar cambio de equipo para {{ $item['customerName'] }}"
                                                    >
                                                        Aplicar
                                                    </x-filament::button>
                                                </label>

                                                <label class="biz-stack biz-stack-xxs">
                                                    <span class="biz-muted">Mover sede</span>
                                                    <select wire:model.live="pendingLocationIds.{{ $item['id'] }}" class="fi-input">
                                                        <option value="">Sin sede</option>
                                                        @foreach ($locationOptions as $locationId => $label)
                                                            <option value="{{ $locationId }}" @selected($item['locationId'] === (string) $locationId)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-filament::button
                                                        size="sm"
                                                        color="gray"
                                                        wire:click="applyLocationChange({{ $item['id'] }})"
                                                        wire:confirm="¿Confirmas mover este caso de sede?"
                                                        aria-label="Aplicar cambio de sede para {{ $item['customerName'] }}"
                                                    >
                                                        Aplicar
                                                    </x-filament::button>
                                                </label>
                                            </div>

                                            <div class="biz-button-row biz-row-end">
                                                @if ($item['status'] === 'open')
                                                    <x-filament::button color="info" wire:click="take({{ $item['id'] }})" aria-label="Tomar conversación de {{ $item['customerName'] }}">
                                                        Tomar
                                                    </x-filament::button>
                                                @endif
                                                @if ($item['status'] !== 'pending_customer')
                                                    <x-filament::button color="success" wire:click="resolve({{ $item['id'] }})" wire:confirm="¿Confirmas resolver este caso y sacarlo de la cola activa?" aria-label="Resolver conversación de {{ $item['customerName'] }}">
                                                        Resolver
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="biz-stack">
                        @if ($selectedConversation === [])
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Hilo',
                                'heading' => 'Selecciona un caso',
                                'description' => 'Abre una conversación de la cola para trabajar el hilo, el journey y la respuesta sugerida desde aquí.',
                            ])
                        @else
                            <div class="biz-card-accent">
                                <div class="biz-stack">
                                    <div class="biz-split biz-split-start">
                                        <div class="biz-stack biz-stack-xs">
                                            <p class="biz-kicker">Hilo conversacional</p>
                                            <h2 class="biz-heading">{{ $selectedConversation['customerName'] }}</h2>
                                            <p class="biz-copy">{{ $selectedConversation['body'] }}</p>
                                        </div>

                                        <div class="biz-button-row biz-row-end">
                                            <span class="biz-badge {{ $selectedConversation['slaColor'] === 'danger' ? 'biz-badge-danger' : ($selectedConversation['slaColor'] === 'success' ? 'biz-badge-success' : 'biz-badge-neutral') }}">{{ $selectedConversation['slaLabel'] }}</span>
                                            <a href="{{ $selectedConversation['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
                                        </div>
                                    </div>

                                    <div class="biz-metrics">
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Ultimo cliente</p>
                                            <p class="biz-stat-value biz-stat-value-sm">{{ $selectedConversation['threadSummary']['last_customer_message'] ?? 'Sin dato' }}</p>
                                            <p class="biz-stat-copy">Última señal del cliente en este hilo.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Ultimo equipo</p>
                                            <p class="biz-stat-value biz-stat-value-sm">{{ $selectedConversation['threadSummary']['last_team_message'] ?? 'Sin dato' }}</p>
                                            <p class="biz-stat-copy">Última respuesta o toque enviado desde tu negocio.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Respuestas abiertas</p>
                                            <p class="biz-stat-value">{{ $selectedConversation['threadSummary']['open_replies'] ?? 0 }}</p>
                                            <p class="biz-stat-copy">Mensajes aún pidiendo acción del equipo.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Esperando cliente</p>
                                            <p class="biz-stat-value">{{ $selectedConversation['threadSummary']['pending_customer'] ?? 0 }}</p>
                                            <p class="biz-stat-copy">Toques ya enviados que quedaron esperando reply.</p>
                                        </div>
                                    </div>

                                    <div class="biz-stack biz-stack-xxs">
                                        <p class="biz-muted"><strong>Segmento:</strong> {{ $selectedConversation['segmentLabel'] }} | <strong>Fidelidad:</strong> {{ $selectedConversation['loyaltyTier'] }}</p>
                                        <p class="biz-muted"><strong>Promesa del segmento:</strong> {{ $selectedConversation['segmentPromise'] }}</p>
                                        <p class="biz-muted"><strong>Siguiente toque:</strong> {{ $selectedConversation['nextTouch'] }}</p>
                                        <p class="biz-muted"><strong>Owner:</strong> {{ $selectedConversation['ownerLabel'] }} | <strong>Sede:</strong> {{ $selectedConversation['locationLabel'] }} | <strong>Equipo:</strong> {{ $selectedConversation['assignmentGroupLabel'] }}</p>
                                        <p class="biz-muted">
                                            <strong>Estado:</strong> {{ $selectedConversation['statusLabel'] }}
                                            | <strong>Prioridad:</strong> {{ $selectedConversation['priorityLabel'] }}
                                            | <strong>Canal:</strong> {{ $selectedConversation['channelLabel'] }}
                                            @if ($selectedConversation['responseDueAt'])
                                                | <strong>Vence:</strong> {{ $selectedConversation['responseDueAt'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="biz-grid-two biz-grid-start">
                                <div class="biz-card">
                                    <div class="biz-stack">
                                    <div>
                                        <p class="biz-kicker">Journey activo</p>
                                        <h3 class="biz-heading">Qué debería pasar ahora</h3>
                                    </div>

                                    @if ($selectedConversation['journeyRun'])
                                        <div class="biz-action">
                                            <div class="biz-button-row">
                                                <span class="biz-badge biz-badge-neutral">{{ $selectedConversation['journeyRun']['statusLabel'] }}</span>
                                                <span class="biz-badge {{ str_contains(strtolower($selectedConversation['journeyRun']['sequenceStateLabel']), 'rescate') ? 'biz-badge-danger' : 'biz-badge-info' }}">{{ $selectedConversation['journeyRun']['sequenceStateLabel'] }}</span>
                                                <span class="biz-badge {{ str_contains(strtolower($selectedConversation['journeyRun']['dueStateLabel']), 'vencido') ? 'biz-badge-danger' : 'biz-badge-neutral' }}">{{ $selectedConversation['journeyRun']['dueStateLabel'] }}</span>
                                            </div>
                                            <p class="biz-action-title">{{ $selectedConversation['journeyRun']['currentStep'] ?? 'Sin paso activo' }}</p>
                                            <p class="biz-action-copy">
                                                {{ $selectedConversation['journeyRun']['currentStepCopy'] ?? 'La secuencia ya está sincronizada con el mejor siguiente paso para esta conversación.' }}
                                            </p>
                                            <p class="biz-muted"><strong>Ruta actual:</strong> {{ $selectedConversation['journeyRun']['currentBranchLabel'] ?? 'Ruta base' }} | <strong>Secuencia:</strong> {{ $selectedConversation['journeyRun']['currentSequenceLabel'] ?? 'Secuencia principal' }}</p>
                                            @if ($selectedConversation['journeyRun']['currentLane'])
                                                <p class="biz-muted"><strong>Lane:</strong> {{ str($selectedConversation['journeyRun']['currentLane'])->replace('_', ' ')->title() }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextActionAt'])
                                                <p class="biz-muted"><strong>Siguiente acción:</strong> {{ $selectedConversation['journeyRun']['nextActionAt'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['stepDueAt'])
                                                <p class="biz-muted"><strong>Paso vence:</strong> {{ $selectedConversation['journeyRun']['stepDueAt'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextStep'])
                                                <p class="biz-muted"><strong>Paso siguiente:</strong> {{ $selectedConversation['journeyRun']['nextStep'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextBranchLabel'] || $selectedConversation['journeyRun']['nextSequenceLabel'])
                                                <p class="biz-muted"><strong>Ruta siguiente:</strong> {{ $selectedConversation['journeyRun']['nextBranchLabel'] ?? 'Ruta base' }} | <strong>Secuencia siguiente:</strong> {{ $selectedConversation['journeyRun']['nextSequenceLabel'] ?? 'Sin siguiente secuencia' }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextTriggerAt'])
                                                <p class="biz-muted"><strong>Disparo automático estimado:</strong> {{ $selectedConversation['journeyRun']['nextTriggerAt'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['lastBusinessSignal'])
                                                <p class="biz-muted"><strong>Última señal de negocio:</strong> {{ $selectedConversation['journeyRun']['lastBusinessSignal'] }}@if($selectedConversation['journeyRun']['lastBusinessSignalAt']) | {{ $selectedConversation['journeyRun']['lastBusinessSignalAt'] }}@endif</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['recommendedTemplateKey'])
                                                <p class="biz-muted"><strong>Plantilla guia:</strong> {{ str($selectedConversation['journeyRun']['recommendedTemplateKey'])->replace('_', ' ')->replace('-', ' ')->title() }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($selectedConversation['journey'] === [])
                                        <p class="biz-muted">Este segmento no tiene un journey cargado todavía.</p>
                                    @else
                                            <div class="biz-actions">
                                                @foreach ($selectedConversation['journey'] as $step)
                                                    <div class="biz-action">
                                                        <p class="biz-action-title">{{ $step['title'] }}</p>
                                                        <p class="biz-action-copy">{{ $step['copy'] }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="biz-card">
                                    <div class="biz-stack">
                                        <div>
                                            <p class="biz-kicker">Plantillas productizadas</p>
                                            <h3 class="biz-heading">Respuestas y siguiente paso</h3>
                                            <p class="biz-muted">Usa la mejor salida para este segmento sin perder ownership, journey ni contexto del hilo.</p>
                                        </div>

                                        <div class="biz-actions">
                                            @foreach ($selectedConversation['templates'] as $template)
                                                <div class="biz-action">
                                                    <div class="biz-split biz-split-start">
                                                        <div class="biz-stack biz-stack-xxs biz-flex-1">
                                                            <div class="biz-button-row">
                                                                <span class="biz-badge biz-badge-neutral">{{ $template['channel_label'] }}</span>
                                                                @if ($template['requires_marketing_opt_in'])
                                                                    <span class="biz-badge biz-badge-warning">Marketing opt-in</span>
                                                                @endif
                                                            </div>
                                                            <p class="biz-action-title">{{ $template['label'] }}</p>
                                                            <p class="biz-action-copy">{{ $template['description'] }}</p>
                                                            @if ($template['warning'])
                                                                <p class="biz-muted"><strong>Bloqueo:</strong> {{ $template['warning'] }}</p>
                                                            @endif
                                                        </div>

                                                        <div class="biz-button-row biz-row-end">
                                                            @if ($template['available'])
                                                                <x-filament::button color="info" wire:click="sendSuggestedReply({{ $selectedConversation['id'] }}, '{{ $template['key'] }}')" wire:confirm="¿Confirmas enviar esta plantilla al cliente?" aria-label="Enviar plantilla {{ $template['label'] }} a {{ $selectedConversation['customerName'] }}">
                                                                    Enviar
                                                                </x-filament::button>
                                                            @else
                                                                <x-filament::button color="gray" disabled aria-label="Plantilla {{ $template['label'] }} no disponible para {{ $selectedConversation['customerName'] }}">
                                                                    No disponible
                                                                </x-filament::button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="biz-card">
                                <div class="biz-stack">
                                    <div>
                                        <p class="biz-kicker">Operación por cliente</p>
                                        <h3 class="biz-heading">Tarea y SLA</h3>
                                    </div>

                                    @if ($selectedConversation['task'])
                                        <div class="biz-action">
                                            <p class="biz-action-title">{{ $selectedConversation['task']['title'] }}</p>
                                            <p class="biz-action-copy">
                                                Estado {{ $selectedConversation['task']['statusLabel'] }} | Prioridad {{ $selectedConversation['task']['priorityLabel'] }} | SLA {{ $selectedConversation['task']['slaLabel'] }}
                                            </p>
                                            @if ($selectedConversation['task']['rescueReason'])
                                                <p class="biz-muted"><strong>Motivo de intervencion:</strong> {{ $selectedConversation['task']['rescueReason'] }}</p>
                                            @elseif ($selectedConversation['task']['body'])
                                                <p class="biz-muted">{{ $selectedConversation['task']['body'] }}</p>
                                            @endif
                                            <p class="biz-muted">
                                                <strong>Equipo:</strong> {{ $selectedConversation['task']['assignmentGroupLabel'] }}
                                                | <strong>Owner:</strong> {{ $selectedConversation['task']['ownerLabel'] }}
                                                | <strong>Sede:</strong> {{ $selectedConversation['task']['locationLabel'] }}
                                                @if ($selectedConversation['task']['dueAt'])
                                                    | <strong>Due:</strong> {{ $selectedConversation['task']['dueAt'] }}
                                                @endif
                                                @if ($selectedConversation['task']['slaDueAt'])
                                                    | <strong>Limite:</strong> {{ $selectedConversation['task']['slaDueAt'] }}
                                                @endif
                                            </p>
                                        </div>
                                    @else
                                        <p class="biz-muted">Esta conversación todavía no tiene una tarea ligada aparte del seguimiento base.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="biz-card">
                                <div class="biz-stack">
                                    <div>
                                        <p class="biz-kicker">Notas internas</p>
                                        <h3 class="biz-heading">Coordinacion del equipo</h3>
                                        <p class="biz-muted">Estas notas no se envian al cliente. Sirven para pasar contexto, decision y siguiente paso entre owner y sede.</p>
                                    </div>

                                    <label class="biz-stack biz-stack-xs">
                                        <span class="biz-muted">Nueva nota interna</span>
                                        <textarea wire:model.defer="draftInternalNote" rows="4" class="fi-input" placeholder="Ejemplo: cliente listo para cierre si confirmamos stock en sucursal sur."></textarea>
                                    </label>

                                    <div class="biz-button-row biz-row-end">
                                        <x-filament::button color="gray" wire:click="addInternalNote({{ $selectedConversation['id'] }})" aria-label="Guardar nota interna para {{ $selectedConversation['customerName'] }}">
                                            Guardar nota interna
                                        </x-filament::button>
                                    </div>

                                    @if ($selectedConversation['internalNotes'] === [])
                                        @include('filament.app.components.table-empty-state', [
                                            'badge' => 'Notas',
                                            'heading' => 'Todavía no hay notas internas',
                                            'description' => 'Guarda contexto, decisión o siguiente paso para que otro owner pueda continuar sin perder información.',
                                        ])
                                    @else
                                        <div class="biz-actions">
                                            @foreach ($selectedConversation['internalNotes'] as $item)
                                                <div class="biz-action">
                                                    <p class="biz-action-title">{{ $item['title'] }}</p>
                                                    <p class="biz-action-copy">{{ $item['body'] }}</p>
                                                    <p class="biz-muted"><strong>{{ $item['author'] }}</strong> | {{ $item['when'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="biz-card">
                                <div class="biz-stack">
                                    <div>
                                        <p class="biz-kicker">Timeline vivo</p>
                                        <h3 class="biz-heading">Hilo del cliente</h3>
                                    </div>

                                    @if ($selectedConversation['threadItems'] === [])
                                        @include('filament.app.components.table-empty-state', [
                                            'badge' => 'Timeline',
                                            'heading' => 'Todavía no hay eventos suficientes',
                                            'description' => 'Cuando lleguen mensajes, notas o respuestas sugeridas, aquí se ordenará el historial del cliente.',
                                        ])
                                    @else
                                        <div class="biz-actions">
                                            @foreach ($selectedConversation['threadItems'] as $item)
                                                <div class="biz-action">
                                                    <div class="biz-button-row">
                                                        <span class="biz-badge {{ $item['direction'] === 'customer' ? 'biz-badge-warning' : 'biz-badge-info' }}">
                                                            {{ $item['direction'] === 'customer' ? 'Cliente' : 'Equipo' }}
                                                        </span>
                                                        <span class="biz-badge biz-badge-neutral">{{ $item['channel'] }}</span>
                                                        @if ($item['thread'])
                                                            <span class="biz-badge biz-badge-neutral">Thread {{ \Illuminate\Support\Str::limit($item['thread'], 16, '...') }}</span>
                                                        @endif
                                                        <span class="biz-badge {{ $item['color'] === 'danger' ? 'biz-badge-danger' : ($item['color'] === 'success' ? 'biz-badge-success' : 'biz-badge-neutral') }}">{{ $item['when'] }}</span>
                                                    </div>
                                                    <p class="biz-action-title">{{ $item['title'] }}</p>
                                                    <p class="biz-action-copy">{{ $item['body'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
