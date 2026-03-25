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
                            Trabaja replies, opt-in y continuidad desde una sola cola. Aqui importa responder bien, a tiempo y con ownership claro.
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
                            <h2 class="biz-heading">Respuesta clara, seguimiento corto, cierre visible</h2>
                            <p class="biz-muted">
                                Usa el segmento, el SLA, la sede y el owner del cliente para decidir el siguiente toque.
                            </p>
                        </div>

                        <div class="biz-actions">
                            <div class="biz-action">
                                <p class="biz-action-title">Prospectos</p>
                                <p class="biz-action-copy">Responder hoy con CTA simple y cerrar primera conversion.</p>
                            </div>
                            <div class="biz-action">
                                <p class="biz-action-title">Recurrentes</p>
                                <p class="biz-action-copy">Mover recompra, continuidad o bundle antes de que se enfrien.</p>
                            </div>
                            <div class="biz-action">
                                <p class="biz-action-title">VIP y loyalty</p>
                                <p class="biz-action-copy">Priorizar experiencia, exclusividad y un siguiente canje visible.</p>
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
                        <p class="biz-kicker">Cola activa</p>
                        <h2 class="biz-heading">Seguimiento por cliente</h2>
                        <p class="biz-muted">
                            Filtra por owner, sede y SLA; luego toma, reasigna o resuelve desde la misma cola.
                        </p>
                    </div>

                    <div class="biz-grid-two" style="grid-template-columns: repeat(2, minmax(12rem, 1fr)); gap: 0.75rem; width: min(100%, 34rem);">
                        <label class="biz-stack" style="gap: 0.35rem;">
                            <span class="biz-muted">Vista</span>
                            <select wire:model.live="selectedScope" class="fi-input">
                                <option value="all">Toda la cola</option>
                                <option value="mine">Solo mi cola</option>
                                <option value="unassigned">Sin owner</option>
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

                @if ($queue === [])
                    <div class="biz-stat">
                        <p class="biz-stat-label">Todo al dia</p>
                        <p class="biz-stat-copy">No hay respuestas pendientes con este filtro.</p>
                    </div>
                @else
                    <div class="biz-actions">
                        @foreach ($queue as $item)
                            <div class="biz-action">
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
                                    </div>

                                    <div class="biz-button-row" style="justify-content: flex-end;">
                                        <a href="{{ $item['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
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
        </div>
    </div>
</x-filament-panels::page>
