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
                            <p class="biz-stat-copy">Casos que conviene mover antes de cerrar el dia.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Sin owner</p>
                            <p class="biz-stat-value">{{ $unassignedCount }}</p>
                            <p class="biz-stat-copy">Conversaciones que todavia no tienen responsable claro.</p>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Regla operativa</p>
                            <h2 class="biz-heading">Responder con contexto, mover el siguiente paso, dejar huella clara</h2>
                            <p class="biz-muted">
                                Usa segmento, fidelidad, owner, sede y SLA para decidir el mejor toque y no solo el mas rapido.
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
                                <p class="biz-action-title">Reactivacion</p>
                                <p class="biz-action-copy">Baja friccion y deja un motivo concreto para volver esta semana.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                    <div>
                        <p class="biz-kicker">Mesa operativa</p>
                        <h2 class="biz-heading">Cola, hilo y secuencia activa</h2>
                        <p class="biz-muted">
                            Filtra por owner, sede, canal y lane del journey; luego trabaja la conversacion desde el hilo, no solo desde la tarjeta.
                        </p>
                    </div>

                    <div class="biz-grid-two" style="grid-template-columns: repeat(3, minmax(10rem, 1fr)); gap: 0.75rem; width: min(100%, 54rem);">
                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Vista</span>
                            <select wire:model.live="selectedScope" class="fi-input">
                                <option value="all">Toda la cola</option>
                                <option value="mine">Solo mi cola</option>
                                <option value="unassigned">Sin owner</option>
                            </select>
                        </label>

                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Lane</span>
                            <select wire:model.live="selectedQueueLane" class="fi-input">
                                <option value="all">Todo el journey</option>
                                <option value="active">Primer toque</option>
                                <option value="waiting_customer">Esperando cliente</option>
                                <option value="stalled">Requiere rescate</option>
                                <option value="closing">Cierre</option>
                            </select>
                        </label>

                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Canal</span>
                            <select wire:model.live="selectedChannel" class="fi-input">
                                <option value="all">Todos</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="crm">CRM</option>
                            </select>
                        </label>

                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">SLA</span>
                            <select wire:model.live="selectedSlaState" class="fi-input">
                                <option value="all">Todo</option>
                                <option value="breached">Vencido</option>
                                <option value="today">Vence hoy</option>
                                <option value="healthy">En tiempo</option>
                            </select>
                        </label>

                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Owner</span>
                            <select wire:model.live="selectedOwnerId" class="fi-input">
                                <option value="">Todos</option>
                                @foreach ($teamOptions as $userId => $label)
                                    <option value="{{ $userId }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Sede</span>
                            <select wire:model.live="selectedLocationId" class="fi-input">
                                <option value="">Todas</option>
                                @foreach ($locationOptions as $locationId => $label)
                                    <option value="{{ $locationId }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="biz-grid-two" style="grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem;">
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
                                    <p class="biz-muted">Todavia no hay conversaciones activas con lectura por canal.</p>
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
                                    <p class="biz-muted">Todavia no hay cartera suficiente para leer carga por owner.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="biz-card">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Operacion por sede</p>
                                <h3 class="biz-heading">Carga por ubicacion</h3>
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
                                    <p class="biz-muted">Todavia no hay base suficiente por sede para leer operacion distribuida.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="biz-grid-two" style="align-items: flex-start; gap: 1.25rem;">
                    <div class="biz-stack">
                        @if ($queue === [])
                            <div class="biz-stat">
                                <p class="biz-stat-label">Todo al dia</p>
                                <p class="biz-stat-copy">No hay respuestas pendientes con este filtro.</p>
                            </div>
                        @else
                            <div class="biz-actions">
                                @foreach ($queue as $item)
                                    <div class="biz-action" style="{{ $selectedConversationId === $item['id'] ? 'border-color: rgba(14,165,233,.4); box-shadow: 0 0 0 1px rgba(14,165,233,.2) inset;' : '' }}">
                                        <div class="biz-stack" style="gap: 0.75rem;">
                                            <div class="biz-split" style="gap: 0.75rem; align-items: flex-start;">
                                                <div class="biz-stack" style="gap: 0.5rem; flex: 1 1 auto;">
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

                                                    <div class="biz-stack" style="gap: 0.35rem;">
                                                        <p class="biz-muted"><strong>Segmento:</strong> {{ $item['segmentLabel'] }} | <strong>Fidelidad:</strong> {{ $item['loyaltyTier'] }}</p>
                                                        <p class="biz-muted"><strong>Siguiente toque:</strong> {{ $item['nextTouch'] }}</p>
                                                        <p class="biz-muted"><strong>Movimiento loyalty:</strong> {{ $item['loyaltyMove'] }}</p>
                                                        <p class="biz-muted"><strong>Owner:</strong> {{ $item['assignedTo'] ?? 'Sin asignar' }} | <strong>Sede:</strong> {{ $item['locationName'] }}</p>
                                                        <p class="biz-muted"><strong>Journey:</strong> {{ $item['journeyStep'] ?? 'Sin paso activo' }} | <strong>Lane:</strong> {{ str($item['queueLane'])->replace('_', ' ')->title() }} | <strong>Estado secuencia:</strong> {{ $item['sequenceStateLabel'] }}</p>
                                                        <p class="biz-muted"><strong>Plantilla recomendada:</strong> {{ $item['recommendedTemplateLabel'] }} | <strong>Lectura de due:</strong> {{ $item['dueStateLabel'] }}</p>
                                                        <p class="biz-muted">
                                                            <strong>Operador actual:</strong> {{ $item['handledBy'] ?? 'Sin tomar' }}
                                                            @if ($item['nextActionAt'])
                                                                | <strong>Proxima accion:</strong> {{ $item['nextActionAt'] }}
                                                            @endif
                                                            @if ($item['responseDueAt'])
                                                                | <strong>SLA:</strong> {{ $item['responseDueAt'] }}
                                                            @endif
                                                            | <strong>Mensajes:</strong> {{ $item['replyCount'] }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="biz-button-row" style="justify-content: flex-end;">
                                                    <x-filament::button color="{{ $selectedConversationId === $item['id'] ? 'info' : 'gray' }}" wire:click="selectConversation({{ $item['id'] }})">
                                                        Ver hilo
                                                    </x-filament::button>
                                                    <a href="{{ $item['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
                                                </div>
                                            </div>

                                            <div class="biz-grid-two" style="grid-template-columns: repeat(2, minmax(12rem, 1fr)); gap: 0.75rem;">
                                                <label class="biz-stack" style="gap: 0.35rem;">
                                                    <span class="biz-muted">Reasignar owner</span>
                                                    <select wire:change="reassignOwner({{ $item['id'] }}, $event.target.value)" class="fi-input">
                                                        <option value="">Sin owner</option>
                                                        @foreach ($teamOptions as $userId => $label)
                                                            <option value="{{ $userId }}" @selected($item['assignedUserId'] === (string) $userId)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>

                                                <label class="biz-stack" style="gap: 0.35rem;">
                                                    <span class="biz-muted">Mover sede</span>
                                                    <select wire:change="moveLocation({{ $item['id'] }}, $event.target.value)" class="fi-input">
                                                        <option value="">Sin sede</option>
                                                        @foreach ($locationOptions as $locationId => $label)
                                                            <option value="{{ $locationId }}" @selected($item['locationId'] === (string) $locationId)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                            </div>

                                            <div class="biz-button-row" style="justify-content: flex-end;">
                                                @if ($item['status'] === 'open')
                                                    <x-filament::button color="info" wire:click="take({{ $item['id'] }})">
                                                        Tomar
                                                    </x-filament::button>
                                                @endif
                                                @if ($item['status'] !== 'pending_customer')
                                                    <x-filament::button color="success" wire:click="resolve({{ $item['id'] }})">
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
                            <div class="biz-stat">
                                <p class="biz-stat-label">Selecciona un caso</p>
                                <p class="biz-stat-copy">Abre una conversacion de la cola para trabajar el hilo, el journey y la respuesta sugerida desde aqui.</p>
                            </div>
                        @else
                            <div class="biz-card-accent">
                                <div class="biz-stack">
                                    <div class="biz-split" style="gap: 0.75rem; align-items: flex-start;">
                                        <div class="biz-stack" style="gap: 0.5rem;">
                                            <p class="biz-kicker">Hilo conversacional</p>
                                            <h2 class="biz-heading">{{ $selectedConversation['customerName'] }}</h2>
                                            <p class="biz-copy">{{ $selectedConversation['body'] }}</p>
                                        </div>

                                        <div class="biz-button-row" style="justify-content: flex-end;">
                                            <span class="biz-badge {{ $selectedConversation['slaColor'] === 'danger' ? 'biz-badge-danger' : ($selectedConversation['slaColor'] === 'success' ? 'biz-badge-success' : 'biz-badge-neutral') }}">{{ $selectedConversation['slaLabel'] }}</span>
                                            <a href="{{ $selectedConversation['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
                                        </div>
                                    </div>

                                    <div class="biz-metrics" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Ultimo cliente</p>
                                            <p class="biz-stat-value" style="font-size: 1rem;">{{ $selectedConversation['threadSummary']['last_customer_message'] ?? 'Sin dato' }}</p>
                                            <p class="biz-stat-copy">Ultima senal del cliente en este hilo.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Ultimo equipo</p>
                                            <p class="biz-stat-value" style="font-size: 1rem;">{{ $selectedConversation['threadSummary']['last_team_message'] ?? 'Sin dato' }}</p>
                                            <p class="biz-stat-copy">Ultima respuesta o toque enviado desde tu negocio.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Respuestas abiertas</p>
                                            <p class="biz-stat-value">{{ $selectedConversation['threadSummary']['open_replies'] ?? 0 }}</p>
                                            <p class="biz-stat-copy">Mensajes aun pidiendo accion del equipo.</p>
                                        </div>
                                        <div class="biz-stat">
                                            <p class="biz-stat-label">Esperando cliente</p>
                                            <p class="biz-stat-value">{{ $selectedConversation['threadSummary']['pending_customer'] ?? 0 }}</p>
                                            <p class="biz-stat-copy">Toques ya enviados que quedaron esperando reply.</p>
                                        </div>
                                    </div>

                                    <div class="biz-stack" style="gap: 0.35rem;">
                                        <p class="biz-muted"><strong>Segmento:</strong> {{ $selectedConversation['segmentLabel'] }} | <strong>Fidelidad:</strong> {{ $selectedConversation['loyaltyTier'] }}</p>
                                        <p class="biz-muted"><strong>Promesa del segmento:</strong> {{ $selectedConversation['segmentPromise'] }}</p>
                                        <p class="biz-muted"><strong>Siguiente toque:</strong> {{ $selectedConversation['nextTouch'] }}</p>
                                        <p class="biz-muted"><strong>Owner:</strong> {{ $selectedConversation['ownerLabel'] }} | <strong>Sede:</strong> {{ $selectedConversation['locationLabel'] }}</p>
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

                            <div class="biz-grid-two" style="align-items: flex-start; gap: 1rem;">
                                <div class="biz-card">
                                    <div class="biz-stack">
                                    <div>
                                        <p class="biz-kicker">Journey activo</p>
                                        <h3 class="biz-heading">Que deberia pasar ahora</h3>
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
                                                {{ $selectedConversation['journeyRun']['currentStepCopy'] ?? 'La secuencia ya esta sincronizada con el mejor siguiente paso para esta conversacion.' }}
                                            </p>
                                            @if ($selectedConversation['journeyRun']['currentLane'])
                                                <p class="biz-muted"><strong>Lane:</strong> {{ str($selectedConversation['journeyRun']['currentLane'])->replace('_', ' ')->title() }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextActionAt'])
                                                <p class="biz-muted"><strong>Siguiente accion:</strong> {{ $selectedConversation['journeyRun']['nextActionAt'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['stepDueAt'])
                                                <p class="biz-muted"><strong>Paso vence:</strong> {{ $selectedConversation['journeyRun']['stepDueAt'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['nextStep'])
                                                <p class="biz-muted"><strong>Paso siguiente:</strong> {{ $selectedConversation['journeyRun']['nextStep'] }}</p>
                                            @endif
                                            @if ($selectedConversation['journeyRun']['recommendedTemplateKey'])
                                                <p class="biz-muted"><strong>Plantilla guia:</strong> {{ str($selectedConversation['journeyRun']['recommendedTemplateKey'])->replace('_', ' ')->replace('-', ' ')->title() }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($selectedConversation['journey'] === [])
                                        <p class="biz-muted">Este segmento no tiene un journey cargado todavia.</p>
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
                                                    <div class="biz-split" style="gap: 0.75rem; align-items: flex-start;">
                                                        <div class="biz-stack" style="gap: 0.35rem; flex: 1 1 auto;">
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

                                                        <div class="biz-button-row" style="justify-content: flex-end;">
                                                            @if ($template['available'])
                                                                <x-filament::button color="info" wire:click="sendSuggestedReply({{ $selectedConversation['id'] }}, '{{ $template['key'] }}')">
                                                                    Enviar
                                                                </x-filament::button>
                                                            @else
                                                                <x-filament::button color="gray" disabled>
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
                                        <p class="biz-kicker">Operacion por cliente</p>
                                        <h3 class="biz-heading">Tarea y SLA</h3>
                                    </div>

                                    @if ($selectedConversation['task'])
                                        <div class="biz-action">
                                            <p class="biz-action-title">{{ $selectedConversation['task']['title'] }}</p>
                                            <p class="biz-action-copy">
                                                Estado {{ $selectedConversation['task']['statusLabel'] }} | Prioridad {{ $selectedConversation['task']['priorityLabel'] }} | SLA {{ $selectedConversation['task']['slaLabel'] }}
                                            </p>
                                            <p class="biz-muted">
                                                <strong>Owner:</strong> {{ $selectedConversation['task']['ownerLabel'] }}
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
                                        <p class="biz-muted">Esta conversacion todavia no tiene una tarea ligada aparte del seguimiento base.</p>
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

                                    <label class="biz-stack" style="gap: 0.5rem;">
                                        <span class="biz-muted">Nueva nota interna</span>
                                        <textarea wire:model.defer="draftInternalNote" rows="4" class="fi-input" placeholder="Ejemplo: cliente listo para cierre si confirmamos stock en sucursal sur."></textarea>
                                    </label>

                                    <div class="biz-button-row" style="justify-content: flex-end;">
                                        <x-filament::button color="gray" wire:click="addInternalNote({{ $selectedConversation['id'] }})">
                                            Guardar nota interna
                                        </x-filament::button>
                                    </div>

                                    @if ($selectedConversation['internalNotes'] === [])
                                        <p class="biz-muted">Todavia no hay notas internas separadas para esta conversacion.</p>
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
                                        <p class="biz-muted">Todavia no hay eventos suficientes para construir el hilo.</p>
                                    @else
                                        <div class="biz-actions">
                                            @foreach ($selectedConversation['threadItems'] as $item)
                                                <div class="biz-action">
                                                    <div class="biz-button-row">
                                                        <span class="biz-badge {{ $item['direction'] === 'customer' ? 'biz-badge-warning' : 'biz-badge-info' }}">
                                                            {{ $item['direction'] === 'customer' ? 'Cliente' : 'Equipo' }}
                                                        </span>
                                                        <span class="biz-badge biz-badge-neutral">{{ $item['channel'] }}</span>
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
