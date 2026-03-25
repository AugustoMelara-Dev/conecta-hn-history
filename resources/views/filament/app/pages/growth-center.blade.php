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
                            <h2 class="biz-heading">CRM, analitica y ejecucion</h2>
                            <p class="biz-muted">
                                Usa este frente para decidir que mover primero, no solo para mirar datos.
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
                        <h2 class="biz-heading">Pipeline y conversion</h2>
                        <p class="biz-muted">{{ $crmCopy }}</p>
                    </div>

                    <div class="biz-metrics">
                        @foreach ($pipelineMetrics as $metric)
                            <div class="biz-stat">
                                <div class="biz-split">
                                    <p class="biz-stat-label">{{ $metric['label'] }}</p>
                                    <x-filament::badge color="{{ $metric['color'] }}">{{ $metric['value'] }}</x-filament::badge>
                                </div>
                                <p class="biz-stat-copy" style="margin-top: 0.75rem;">{{ $metric['copy'] }}</p>
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
                        <h2 class="biz-heading">Clientes, audiencias y retencion</h2>
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
                        <p class="biz-kicker">Analitica PYME profunda</p>
                        <h2 class="biz-heading">Recurrencia, entregabilidad y oferta top</h2>
                        <p class="biz-muted">Lee si tu CRM esta llegando, si tus clientes regresan y que oferta esta empujando mas caja.</p>
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
                        <a href="{{ $campaignsUrl }}" class="biz-button-secondary">Ver campanas</a>
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
                            <div class="biz-action">
                                <p class="biz-action-title">Todavia no hay playbooks de cadence configurados</p>
                                <p class="biz-action-copy">Define una cadencia basica por segmento y recompensa para sostener recompra, reactivacion y upsell.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $campaignsUrl }}" class="biz-button">Programar campanas</a>
                        <a href="{{ $customersUrl }}" class="biz-button-secondary">Ver segmentos</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Cohortes y LTV</p>
                        <h2 class="biz-heading">Lectura de valor temprano y ritmo de repeticion</h2>
                        <p class="biz-muted">Esto te dice si las cohortes nuevas se estan activando bien y cuales segmentos o canales dejan mejor valor.</p>
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
                        <h2 class="biz-heading">Lo que hoy esta empujando el negocio</h2>
                    </div>

                    <div class="biz-list">
                        <div class="biz-checklist-item">
                            <div class="biz-checklist-index is-complete">1</div>
                            <div>
                                <p class="biz-checklist-title">Fuente mas fuerte</p>
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
                                    {{ $hasWhatsApp ? 'Tu negocio ya tiene un canal rapido para seguimiento y cierre.' : 'Todavia te falta el canal mas directo para convertir visitas en conversaciones.' }}
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
                            <div class="biz-action">
                                <p class="biz-action-title">Tu frente de crecimiento esta ordenado</p>
                                <p class="biz-action-copy">
                                    Sigue usando prospectos, playbooks y campanas para no perder el ritmo comercial.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

