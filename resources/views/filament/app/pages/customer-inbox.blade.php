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
                            Trabaja replies, opt-in y continuidad desde una sola cola. Aqui importa responder bien y a tiempo,
                            no solo enviar automatizaciones.
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
                            <p class="biz-stat-label">Urgentes</p>
                            <p class="biz-stat-value">{{ $urgentCount }}</p>
                            <p class="biz-stat-copy">Casos que no conviene dejar para mañana.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-stat-label">Resueltas hoy</p>
                            <p class="biz-stat-value">{{ $resolvedTodayCount }}</p>
                            <p class="biz-stat-copy">Velocidad de respuesta del negocio en este turno.</p>
                        </div>
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Regla operativa</p>
                            <h2 class="biz-heading">Respuesta clara, seguimiento corto, cierre visible</h2>
                            <p class="biz-muted">
                                Usa el segmento y la fidelidad del cliente para decidir el siguiente toque.
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
                                <p class="biz-action-copy">Priorizar experiencia, exclusividad y alto ticket.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Cola activa</p>
                    <h2 class="biz-heading">Seguimiento por cliente</h2>
                    <p class="biz-muted">
                        Toma el caso, mueve la conversación y ciérralo cuando ya no requiera seguimiento.
                    </p>
                </div>

                @if ($queue === [])
                    <div class="biz-stat">
                        <p class="biz-stat-label">Todo al día</p>
                        <p class="biz-stat-copy">No hay respuestas pendientes en este momento.</p>
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
                                            <span class="biz-badge {{ $item['status'] === 'resolved' ? 'biz-badge-success' : ($item['status'] === 'in_progress' ? 'biz-badge-info' : 'biz-badge-warning') }}">{{ $item['statusLabel'] }}</span>
                                        </div>

                                        <div>
                                            <p class="biz-action-title">{{ $item['customerName'] }}</p>
                                            <p class="biz-action-copy">{{ $item['body'] }}</p>
                                        </div>

                                        <div class="biz-stack" style="gap: 0.35rem;">
                                            <p class="biz-muted"><strong>Segmento:</strong> {{ $item['segmentLabel'] }} | <strong>Fidelidad:</strong> {{ $item['loyaltyTier'] }}</p>
                                            <p class="biz-muted"><strong>Siguiente toque:</strong> {{ $item['nextTouch'] }}</p>
                                            <p class="biz-muted"><strong>Movimiento loyalty:</strong> {{ $item['loyaltyMove'] }}</p>
                                            <p class="biz-muted">
                                                <strong>Owner:</strong> {{ $item['handledBy'] ?? 'Sin asignar' }}
                                                @if ($item['nextActionAt'])
                                                    | <strong>Próxima acción:</strong> {{ $item['nextActionAt'] }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="biz-button-row" style="justify-content: flex-end;">
                                        <a href="{{ $item['profileUrl'] }}" class="biz-button-secondary">Abrir ficha</a>
                                        @if ($item['status'] === 'open')
                                            <x-filament::button color="info" wire:click="take({{ $item['id'] }})">
                                                Tomar
                                            </x-filament::button>
                                        @endif
                                        <x-filament::button color="success" wire:click="resolve({{ $item['id'] }})">
                                            Resolver
                                        </x-filament::button>
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
