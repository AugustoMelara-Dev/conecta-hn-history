<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-button-row">
                        <div class="biz-badge biz-badge-neutral">
                            <span>{{ $businessTypeLabel }}</span>
                        </div>
                        <div class="biz-badge biz-badge-neutral">
                            <span>{{ $segmentLabel }}</span>
                        </div>
                        <div class="biz-badge {{ $isPro ? 'biz-badge-success' : 'biz-badge-warning' }}">
                            <span>{{ $isPro ? 'PRO' : 'Base' }}</span>
                        </div>
                    </div>

                    <div class="biz-stack">
                        <p class="biz-kicker">Centro de crecimiento</p>
                        <h1 class="biz-title">{{ $tenantName }}</h1>
                        <p class="biz-heading">{{ $headline }}</p>
                        <p class="biz-copy">{{ $copy }}</p>
                    </div>

                    <div class="biz-metrics">
                        @foreach ($revenueMetrics as $metric)
                            <div class="biz-stat">
                                <p class="biz-stat-label">{{ $metric['label'] }}</p>
                                <p class="biz-stat-value">{{ $metric['value'] }}</p>
                                <p class="biz-stat-copy">{{ $metric['copy'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Palancas inmediatas</p>
                            <h2 class="biz-heading">CRM, analítica y ejecución</h2>
                            <p class="biz-muted">
                                Usa este frente para decidir qué mover primero, no solo para mirar datos.
                            </p>
                        </div>

                        <div class="biz-actions">
                            @foreach ($growthActions as $action)
                                <a href="{{ $action['href'] }}" class="biz-action">
                                    <p class="biz-action-title">{{ $action['title'] }}</p>
                                    <p class="biz-action-copy">{{ $action['copy'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">{{ $crmTitle }}</p>
                        <h2 class="biz-heading">Pipeline y conversión</h2>
                        <p class="biz-muted">{{ $crmCopy }}</p>
                    </div>

                    <div class="biz-metrics">
                        @foreach ($pipelineMetrics as $metric)
                            <div class="biz-stat">
                                <div class="biz-split">
                                    <p class="biz-stat-label">{{ $metric['label'] }}</p>
                                    <x-filament::badge color="{{ $metric['color'] }}">{{ $metric['value'] }}</x-filament::badge>
                                </div>
                                <p class="biz-stat-copy biz-mt-3">{{ $metric['copy'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $leadsUrl }}" class="biz-button">Abrir pipeline</a>
                        <a href="{{ $operationsUrl }}" class="biz-button-secondary">Ver playbooks</a>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">{{ $analyticsTitle }}</p>
                        <h2 class="biz-heading">Clientes, audiencias y retención</h2>
                        <p class="biz-muted">{{ $analyticsCopy }}</p>
                    </div>

                    <div class="biz-metrics">
                        @foreach ($customerSignals as $signal)
                            <div class="biz-stat">
                                <p class="biz-stat-label">{{ $signal['label'] }}</p>
                                <p class="biz-stat-value">{{ $signal['value'] }}</p>
                                <p class="biz-stat-copy">{{ $signal['copy'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $followersUrl }}" class="biz-button-secondary">Ver audiencia</a>
                        <a href="{{ $hasLoyaltyProgram ? $loyaltyUrl : $couponsUrl }}" class="biz-button-secondary">
                            {{ $hasLoyaltyProgram ? 'Ver loyalty' : 'Activar incentivo' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Analítica PYME profunda</p>
                        <h2 class="biz-heading">Recurrencia, entregabilidad y oferta top</h2>
                        <p class="biz-muted">Lee si tu CRM está llegando, si tus clientes regresan y qué oferta está empujando más caja.</p>
                    </div>

                    <div class="biz-metrics">
                        @foreach ($engagementMetrics as $metric)
                            <div class="biz-stat">
                                <p class="biz-stat-label">{{ $metric['label'] }}</p>
                                <p class="biz-stat-value">{{ $metric['value'] }}</p>
                                <p class="biz-stat-copy">{{ $metric['copy'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $contactsUrl }}" class="biz-button">Ver entregas</a>
                        <a href="{{ $customersUrl }}" class="biz-button-secondary">Trabajar clientes</a>
                        <a href="{{ $campaignsUrl }}" class="biz-button-secondary">Ver campañas</a>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Cadencia comercial</p>
                        <h2 class="biz-heading">Playbooks recomendados por tu vertical</h2>
                    </div>

                    <div class="biz-actions">
                        @forelse ($cadencePlaybooks as $playbook)
                            <a href="{{ $playbook['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-kicker">{{ $playbook['channel'] }}</p>
                                        <p class="biz-action-title">{{ $playbook['title'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $playbook['statusColor'] }}">
                                        {{ $playbook['statusLabel'] }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-action-copy">{{ $playbook['copy'] }}</p>
                                <p class="biz-muted">Cadencia: {{ $playbook['cadence'] }} / Disparo: {{ $playbook['trigger'] }}</p>
                            </a>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Cadencia',
                                'heading' => 'Todavía no hay playbooks configurados',
                                'description' => 'Define una cadencia básica por segmento y recompensa para sostener recompra, reactivación y upsell.',
                            ])
                        @endforelse
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $campaignsUrl }}" class="biz-button">Programar campañas</a>
                        <a href="{{ $customersUrl }}" class="biz-button-secondary">Ver segmentos</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Analítica por plantilla</p>
                    <h2 class="biz-heading">Que mensajes realmente estan moviendo reply y cierre</h2>
                    <p class="biz-muted">Sirve para dejar de enviar por intuición y empezar a repetir las secuencias que sí convierten en revenue real.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($templatePerformance as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['label'] }}</p>
                                    <p class="biz-action-copy">Canal dominante: {{ $item['channel'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $item['conversions'] > 0 ? 'success' : ($item['replied'] > 0 ? 'info' : 'gray') }}">
                                    {{ $item['closeRate'] }} cierre
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>Envios:</strong> {{ $item['sent'] }}
                                | <strong>Replies:</strong> {{ $item['replied'] }} ({{ $item['replyRate'] }})
                                | <strong>Conversiones:</strong> {{ $item['conversions'] }}
                                | <strong>Revenue:</strong> {{ $item['revenue'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Plantillas',
                            'heading' => 'Todavía no hay lectura por plantilla',
                            'description' => 'Hace falta más volumen reciente de mensajes para detectar copy ganador.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Analítica por secuencia</p>
                    <h2 class="biz-heading">Que rutas del journey estan moviendo reply, cierre y revenue</h2>
                    <p class="biz-muted">Sirve para entender si la secuencia correcta es seguimiento, loyalty, rescate o cierre premium, no solo qué texto envió más.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($sequencePerformance as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['label'] }}</p>
                                    <p class="biz-action-copy">{{ $item['branch'] }} / Lane: {{ $item['lane'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $item['conversions'] > 0 ? 'success' : ($item['replied'] > 0 ? 'info' : 'gray') }}">
                                    {{ $item['closeRate'] }} cierre
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>Envios:</strong> {{ $item['sent'] }}
                                | <strong>Replies:</strong> {{ $item['replied'] }} ({{ $item['replyRate'] }})
                                | <strong>Conversiones:</strong> {{ $item['conversions'] }}
                                | <strong>Revenue:</strong> {{ $item['revenue'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Secuencias',
                            'heading' => 'Todavía no hay lectura por secuencia',
                            'description' => 'Hace falta que los journeys empiecen a recorrer rutas distintas para medir qué secuencia gana por segmento y vertical.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Attribution multi-touch</p>
                    <h2 class="biz-heading">Que secuencias se estan llevando revenue real</h2>
                    <p class="biz-muted">Aquí el revenue se reparte entre las rutas que tocaron una conversación cerrada, para no mentirte con un falso last-touch.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($sequenceAttribution as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['label'] }}</p>
                                    <p class="biz-action-copy">Revenue atribuido: {{ $item['revenue'] }}</p>
                                </div>

                                <x-filament::badge color="{{ (float) $item['conversions'] >= 1 ? 'success' : 'info' }}">
                                    {{ $item['conversions'] }} {{ (float) $item['conversions'] === 1.0 ? 'conversión' : 'conversiones' }}
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>Replies:</strong> {{ $item['replies'] }}
                                | <strong>Clicks/reads:</strong> {{ $item['clicks'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Attribution',
                            'heading' => 'Todavía no hay attribution multi-touch suficiente',
                            'description' => 'Hace falta más recorrido de secuencias en conversaciones cerradas para repartir revenue con criterio.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Rutas completas</p>
                    <h2 class="biz-heading">Qué conversaciones completas se están llevando el revenue</h2>
                    <p class="biz-muted">Aquí ya no se reparte solo por secuencia: ves la ruta completa que siguió una conversación hasta cerrar.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($conversationPathAttribution as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['label'] }}</p>
                                    <p class="biz-action-copy">Revenue atribuido: {{ $item['revenue'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $item['conversations'] > 1 ? 'success' : 'info' }}">
                                    {{ $item['conversations'] }} {{ (int) $item['conversations'] === 1 ? 'conversación' : 'conversaciones' }}
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>Owners:</strong> {{ $item['owners'] }}
                                | <strong>Canales:</strong> {{ $item['channels'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Rutas',
                            'heading' => 'Todavía no hay rutas conversacionales cerradas',
                            'description' => 'Hace falta más recorrido de conversaciones multitoque para leer revenue por ruta completa.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Revenue por outcomes reales</p>
                    <h2 class="biz-heading">Que secuencias estan cerrando compra, recuperando no-show y moviendo recompra</h2>
                    <p class="biz-muted">Aquí ya no solo ves reply o click: ves outcomes de negocio reales ligados a la secuencia.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($sequenceOutcomeAttribution as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['label'] }}</p>
                                    <p class="biz-action-copy">Revenue atribuido: {{ $item['revenue'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $item['purchaseClosures'] > 0 ? 'success' : ($item['upgradeAssists'] > 0 ? 'info' : 'gray') }}">
                                    {{ $item['purchaseClosures'] }} compras
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>No-show recuperado:</strong> {{ $item['noShowRecovered'] }}
                                | <strong>Recompra tras reward:</strong> {{ $item['rewardRepurchases'] }}
                                | <strong>Upgrade assist:</strong> {{ $item['upgradeAssists'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Outcomes',
                            'heading' => 'Todavía no hay outcomes reales suficientes por secuencia',
                            'description' => 'Hace falta más cierre, recuperación o recompra real para abrir esta lectura.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Revenue atribuido por owner</p>
                        <h2 class="biz-heading">Quién está empujando las secuencias que sí convierten</h2>
                        <p class="biz-muted">Cruza revenue multi-touch con ownership para detectar quién mueve mejor cada ruta del CRM.</p>
                    </div>

                    <div class="biz-actions">
                        @forelse ($ownerSequenceAttribution as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">{{ $item['context'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ (float) $item['conversions'] >= 1 ? 'success' : 'info' }}">
                                        {{ $item['conversions'] }} {{ (float) $item['conversions'] === 1.0 ? 'conversión' : 'conversiones' }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Replies:</strong> {{ $item['replies'] }}
                                    | <strong>Clicks/reads:</strong> {{ $item['clicks'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Owners',
                                'heading' => 'Todavía no hay lectura por owner y secuencia',
                                'description' => 'Hace falta más cierre multi-touch con ownership claro para comparar responsables.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Revenue atribuido por sede</p>
                        <h2 class="biz-heading">Donde estan ganando mejor las secuencias</h2>
                        <p class="biz-muted">Compara impacto real por ubicación para operar CRM distribuido con más criterio.</p>
                    </div>

                    <div class="biz-actions">
                        @forelse ($locationSequenceAttribution as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">{{ $item['context'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ (float) $item['conversions'] >= 1 ? 'success' : 'info' }}">
                                        {{ $item['conversions'] }} {{ (float) $item['conversions'] === 1.0 ? 'conversión' : 'conversiones' }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Replies:</strong> {{ $item['replies'] }}
                                    | <strong>Clicks/reads:</strong> {{ $item['clicks'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Sedes',
                                'heading' => 'Todavía no hay lectura por sede y secuencia',
                                'description' => 'Etiqueta más cartera y conversación por sede para comparar impacto real.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Revenue atribuido por canal</p>
                    <h2 class="biz-heading">Qué canal está moviendo mejor cada secuencia</h2>
                    <p class="biz-muted">Sirve para decidir dónde empujar journeys, rescates y cierres con más retorno real.</p>
                </div>

                <div class="biz-actions">
                    @forelse ($channelSequenceAttribution as $item)
                        <div class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-action-title">{{ $item['name'] }}</p>
                                    <p class="biz-action-copy">{{ $item['context'] }}</p>
                                </div>

                                <x-filament::badge color="{{ (float) $item['conversions'] >= 1 ? 'success' : 'info' }}">
                                    {{ $item['conversions'] }} {{ (float) $item['conversions'] === 1.0 ? 'conversión' : 'conversiones' }}
                                </x-filament::badge>
                            </div>

                            <p class="biz-muted">
                                <strong>Revenue:</strong> {{ $item['revenue'] }}
                                | <strong>Replies:</strong> {{ $item['replies'] }}
                                | <strong>Clicks/reads:</strong> {{ $item['clicks'] }}
                            </p>
                        </div>
                    @empty
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Canales',
                            'heading' => 'Todavía no hay lectura por canal y secuencia',
                            'description' => 'Hace falta más volumen multi-touch para comparar WhatsApp, email y CRM con criterio.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Analítica conversacional por owner</p>
                        <h2 class="biz-heading">Quién está cerrando mejor la conversación</h2>
                        <p class="biz-muted">Cruza volumen, conversiones, revenue, conversaciones esperando reply y velocidad inicial por responsable.</p>
                    </div>

                    <div class="biz-actions">
                        @forelse ($ownerConversationPerformance as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">Primera respuesta: {{ $item['avgFirstResponse'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $item['breached'] > 0 ? 'danger' : 'success' }}">
                                        {{ $item['breached'] > 0 ? $item['breached'].' vencidas' : 'SLA sano' }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Total:</strong> {{ $item['total'] }}
                                    | <strong>Conversiones:</strong> {{ $item['conversions'] }}
                                    | <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Tasa:</strong> {{ $item['conversionRate'] }}
                                    | <strong>Esperando:</strong> {{ $item['waiting'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Owners',
                                'heading' => 'Todavía no hay lectura conversacional por owner',
                                'description' => 'Asigna ownership y trabaja más replies desde la bandeja para abrir esta vista.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Analítica conversacional por sede</p>
                        <h2 class="biz-heading">Dónde se está quedando o cerrando la conversación</h2>
                        <p class="biz-muted">Compara rapidez, conversiones, revenue y carga operativa entre sucursales o puntos de atención.</p>
                    </div>

                    <div class="biz-actions">
                        @forelse ($locationConversationPerformance as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">Primera respuesta: {{ $item['avgFirstResponse'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $item['breached'] > 0 ? 'warning' : 'info' }}">
                                        {{ $item['waiting'] }} esperando
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Total:</strong> {{ $item['total'] }}
                                    | <strong>Conversiones:</strong> {{ $item['conversions'] }}
                                    | <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Tasa:</strong> {{ $item['conversionRate'] }}
                                    | <strong>Vencidas:</strong> {{ $item['breached'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Sedes',
                                'heading' => 'Todavía no hay lectura conversacional por sede',
                                'description' => 'Etiqueta más clientes y conversaciones por sede para comparar operación distribuida.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Cohortes y LTV</p>
                        <h2 class="biz-heading">Lectura de valor temprano y ritmo de repetición</h2>
                        <p class="biz-muted">Esto te dice si las cohortes nuevas se están activando bien y cuáles segmentos o canales dejan mejor valor.</p>
                    </div>

                    <div class="biz-badge biz-badge-neutral">
                        <span>{{ $topLtvSegment }} / {{ $topLtvChannel }}</span>
                    </div>
                </div>

                <div class="biz-metrics">
                    @foreach ($cohortMetrics as $metric)
                        <div class="biz-stat">
                            <p class="biz-stat-label">{{ $metric['label'] }}</p>
                            <p class="biz-stat-value">{{ $metric['value'] }}</p>
                            <p class="biz-stat-copy">{{ $metric['copy'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Comparativa por owner</p>
                            <h2 class="biz-heading">Quién está sosteniendo cartera, revenue y SLA</h2>
                            <p class="biz-muted">Lee carga activa, valor acumulado y repetición por responsable.</p>
                        </div>

                        <div class="biz-badge biz-badge-neutral">
                            <span>{{ $openCustomerTasksCount }} tareas abiertas / {{ $overdueCustomerTasksCount }} vencidas</span>
                        </div>
                    </div>

                    <div class="biz-actions">
                        @forelse ($ownerComparisons as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">{{ $item['context'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $item['overdueTasks'] > 0 ? 'danger' : 'success' }}">
                                        {{ $item['overdueTasks'] > 0 ? $item['overdueTasks'].' vencidas' : 'SLA sano' }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Clientes:</strong> {{ $item['customers'] }}
                                    | <strong>Tareas:</strong> {{ $item['openTasks'] }}
                                    | <strong>Repeat:</strong> {{ $item['repeatRate'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Owners',
                                'heading' => 'Todavía no hay comparativa por owner',
                                'description' => 'Asigna cartera y tareas para ver rendimiento comercial por responsable.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Comparativa por sede</p>
                        <h2 class="biz-heading">Donde se concentra mejor la continuidad comercial</h2>
                        <p class="biz-muted">Compara revenue, clientes y carga operativa entre sucursales o puntos de atención.</p>
                    </div>

                    <div class="biz-actions">
                        @forelse ($locationComparisons as $item)
                            <div class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $item['name'] }}</p>
                                        <p class="biz-action-copy">{{ $item['context'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $item['overdueTasks'] > 0 ? 'warning' : 'info' }}">
                                        {{ $item['openTasks'] }} activas
                                    </x-filament::badge>
                                </div>

                                <p class="biz-muted">
                                    <strong>Revenue:</strong> {{ $item['revenue'] }}
                                    | <strong>Clientes:</strong> {{ $item['customers'] }}
                                    | <strong>Vencidas:</strong> {{ $item['overdueTasks'] }}
                                    | <strong>Repeat:</strong> {{ $item['repeatRate'] }}
                                </p>
                            </div>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Sedes',
                                'heading' => 'Todavía no hay comparativa por sede',
                                'description' => 'Etiquetar clientes y tareas por ubicación va a abrir lectura real de operación distribuida.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">{{ $automationTitle }}</p>
                        <h2 class="biz-heading">Automatizaciones externas</h2>
                        <p class="biz-muted">{{ $automationCopy }}</p>
                    </div>

                    <div class="biz-badge biz-badge-neutral">
                        <span>{{ $openPlaybooksCount }} playbooks vivos</span>
                    </div>
                </div>

                <div class="biz-actions">
                    @foreach ($automationJourneys as $journey)
                        <a href="{{ $journey['href'] }}" class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-kicker">{{ $journey['eyebrow'] }}</p>
                                    <p class="biz-action-title">{{ $journey['title'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $journey['statusColor'] }}">
                                    {{ $journey['statusLabel'] }}
                                </x-filament::badge>
                            </div>

                            <p class="biz-action-copy">{{ $journey['copy'] }}</p>
                            <p class="biz-muted">Canal: {{ $journey['channel'] }} / Disparo: {{ $journey['trigger'] }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Contexto comercial</p>
                        <h2 class="biz-heading">Lo que hoy está empujando el negocio</h2>
                    </div>

                    <div class="biz-list">
                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index is-complete">1</div>
                            <div>
                                <p class="biz-checklist-title">Fuente más fuerte</p>
                                <p class="biz-checklist-copy">{{ $topLeadSource }}</p>
                            </div>
                        </div>

                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index is-complete">2</div>
                            <div>
                                <p class="biz-checklist-title">Canal de venta dominante</p>
                                <p class="biz-checklist-copy">{{ $topSalesChannel }}</p>
                            </div>
                        </div>

                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index is-complete">3</div>
                            <div>
                                <p class="biz-checklist-title">Entrega de email</p>
                                <p class="biz-checklist-copy">{{ $deliveryRate30d }}% con {{ $touchpointsBounced30d }} rebotes o quejas recientes.</p>
                            </div>
                        </div>

                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index {{ $hasWhatsApp ? 'is-complete' : 'is-pending' }}">
                                {{ $hasWhatsApp ? 'OK' : '4' }}
                            </div>
                            <div>
                                <p class="biz-checklist-title">Canal de WhatsApp</p>
                                <p class="biz-checklist-copy">
                                    {{ $hasWhatsApp ? 'Tu negocio ya tiene un canal rápido para seguimiento y cierre.' : 'Todavía te falta el canal más directo para convertir visitas en conversaciones.' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Lo que conviene mover hoy</p>
                        <h2 class="biz-heading">Cola ejecutiva del dueno</h2>
                    </div>

                    <div class="biz-actions">
                        @forelse ($priorityQueue as $item)
                            <a href="{{ $item['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <p class="biz-action-title">{{ $item['title'] }}</p>
                                    <x-filament::badge color="{{ $item['severity'] }}">
                                        {{ match ($item['severity']) {
                                            'danger' => 'Urgente',
                                            'warning' => 'Clave',
                                            default => 'Siguiente paso',
                                        } }}
                                    </x-filament::badge>
                                </div>
                                <p class="biz-action-copy">{{ $item['copy'] }}</p>
                            </a>
                        @empty
                            @include('filament.app.components.table-empty-state', [
                                'badge' => 'Crecimiento',
                                'heading' => 'Tu frente de crecimiento está ordenado',
                                'description' => 'Sigue usando prospectos, playbooks y campañas para no perder el ritmo comercial.',
                            ])
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
