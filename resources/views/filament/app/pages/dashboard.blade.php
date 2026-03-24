<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-button-row">
                        <div class="biz-badge {{ $isOpen ? 'biz-badge-success' : 'biz-badge-warning' }}">
                            <span>{{ $isOpen ? 'Abierto' : 'Cerrado' }}</span>
                        </div>

                        <div class="biz-badge biz-badge-neutral">
                            <span>{{ $businessTypeLabel }}</span>
                        </div>

                        <div class="biz-badge biz-badge-neutral">
                            <span>{{ $segmentLabel }}</span>
                        </div>
                    </div>

                    <div class="biz-stack">
                        <p class="biz-kicker">Panel del negocio</p>
                        <h1 class="biz-title">{{ $tenantName }}</h1>
                        <p class="biz-heading">{{ $experienceTitle }}</p>
                        <p class="biz-copy">{{ $experienceCopy }}</p>
                        <p class="biz-muted">{{ $businessTypeDescription }}</p>

                        @if ($segmentTeaser !== '')
                            <p class="biz-muted">{{ $segmentTeaser }}</p>
                        @endif
                    </div>

                    @if ($businessTypeCapabilities !== [])
                        <div class="biz-button-row">
                            @foreach ($businessTypeCapabilities as $capability)
                                <x-filament::badge color="success">{{ $capability }}</x-filament::badge>
                            @endforeach
                        </div>
                    @endif

                    @if ($businessExamples !== [])
                        <p class="biz-muted">
                            Ejemplos: {{ implode(', ', $businessExamples) }}.
                        </p>
                    @endif

                    <div class="biz-metrics">
                        @foreach ($focusMetrics as $metric)
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
                            <p class="biz-kicker">Onboarding por rubro</p>
                            <h2 class="biz-heading">{{ $onboardingTitle }}</h2>
                            <p class="biz-muted">
                                {{ $onboardingCopy }}
                            </p>
                        </div>

                        <div class="biz-actions">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['href'] }}" class="biz-action">
                                    <p class="biz-action-title">{{ $action['title'] }}</p>
                                    <p class="biz-action-copy">{{ $action['copy'] }}</p>
                                </a>
                            @endforeach
                        </div>

                        <div class="biz-button-row">
                            <a href="{{ $publicUrl }}" target="_blank" class="biz-button">Abrir mi página</a>
                            <a href="{{ $profileUrl }}" class="biz-button-secondary">Configurar negocio</a>
                            <a href="{{ $operationsUrl }}" class="biz-button-secondary">Centro operativo</a>
                            <a href="{{ $billingUrl }}" class="biz-button-secondary">Ver plan</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Mesa operativa con SLA</p>
                        <h2 class="biz-heading">Cola viva para seguimiento, agenda y renovacion</h2>
                        <p class="biz-muted">
                            Aqui ves lo que ya disparo el negocio en tiempo real: leads, citas, recuperacion, recompra y renovaciones con responsable sugerido.
                        </p>
                    </div>

                    <a href="{{ $operationsUrl }}" class="biz-button-secondary">Abrir centro operativo</a>
                </div>

                <div class="biz-metrics">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Abiertas</p>
                        <p class="biz-stat-value">{{ $openPlaybookTasksCount }}</p>
                        <p class="biz-stat-copy">Playbooks activos pendientes de mover.</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">En mi cola</p>
                        <p class="biz-stat-value">{{ $myPlaybookTasksCount }}</p>
                        <p class="biz-stat-copy">Frentes asignados al usuario actual.</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">SLA vencido</p>
                        <p class="biz-stat-value">{{ $overduePlaybookTasksCount }}</p>
                        <p class="biz-stat-copy">Casos que merecen atencion inmediata.</p>
                    </div>
                </div>

                @if ($livePlaybookTasks !== [])
                    <div class="biz-actions">
                        @foreach ($livePlaybookTasks as $task)
                            <a href="{{ $task['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-kicker">{{ $task['queue'] }}</p>
                                        <p class="biz-action-title">{{ $task['title'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $task['severity'] }}">
                                        {{ $task['sla'] }}
                                    </x-filament::badge>
                                </div>

                                <p class="biz-action-copy">{{ $task['copy'] }}</p>
                                <p class="biz-muted">Responsable: {{ $task['assignee'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="biz-card-accent">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Operacion lista</p>
                                <h3 class="biz-heading" style="font-size: 1.15rem;">Tu cola esta despejada</h3>
                                <p class="biz-muted">
                                    Cuando entre un lead, una cita, una recompra o una renovacion, aparecera aqui con SLA y responsable sugerido.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Checklist inicial</p>
                        <h2 class="biz-heading">Lo esencial para {{ $segmentLabel }}</h2>
                        <p class="biz-muted">
                            El score resume qué tan listo está tu negocio para verse profesional, operar sin fricción y empezar a crecer.
                        </p>
                    </div>

                    <div class="biz-stat">
                        <div class="biz-split">
                            <div>
                                <p class="biz-stat-label">Score actual</p>
                                <p class="biz-stat-value">{{ $healthScore }}/100</p>
                                <p class="biz-stat-copy">Mientras más alto, más consistente se ve tu operación para el cliente.</p>
                            </div>

                            <x-filament::badge color="{{ $healthScore >= 80 ? 'success' : ($healthScore >= 50 ? 'warning' : 'danger') }}">
                                {{ $healthScore >= 80 ? 'Fuerte' : ($healthScore >= 50 ? 'En progreso' : 'Atención') }}
                            </x-filament::badge>
                        </div>

                        <div class="biz-progress" style="margin-top: 0.9rem;">
                            <div
                                class="biz-progress-bar {{ $healthScore < 50 ? 'biz-progress-bar-danger' : ($healthScore < 80 ? 'biz-progress-bar-warning' : '') }}"
                                style="width: {{ max(6, $healthCompletionPercent) }}%;"
                            ></div>
                        </div>
                    </div>

                    <div class="biz-list">
                        @foreach ($healthChecks as $index => $check)
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index {{ $check['complete'] ? 'is-complete' : 'is-pending' }}">
                                    {{ $check['complete'] ? 'OK' : $index + 1 }}
                                </div>
                                <div>
                                    <p class="biz-checklist-title">{{ $check['title'] }}</p>
                                    <p class="biz-checklist-copy">{{ $check['copy'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Prioridades de hoy</p>
                        <h2 class="biz-heading">Qué conviene mover primero</h2>
                        <p class="biz-copy">
                            Esta cola corta te ayuda a actuar como operador, no solo como editor del panel.
                        </p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($todayQueue as $item)
                            <a href="{{ $item['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <p class="biz-action-title">{{ $item['title'] }}</p>

                                    <x-filament::badge color="{{ $item['severity'] }}">
                                        {{ match ($item['severity']) {
                                            'danger' => 'Urgente',
                                            'warning' => 'Clave',
                                            'info' => 'Siguiente paso',
                                            default => 'Bien',
                                        } }}
                                    </x-filament::badge>
                                </div>
                                <p class="biz-action-copy">{{ $item['copy'] }}</p>
                            </a>
                        @endforeach
                    </div>

                    @if ($growthNudges !== [])
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Empuje comercial</p>
                                <h3 class="biz-heading" style="font-size: 1.15rem;">Nudges de crecimiento</h3>
                            </div>

                            <div class="biz-actions">
                                @foreach ($growthNudges as $nudge)
                                    <a href="{{ $nudge['href'] }}" class="biz-action">
                                        <p class="biz-action-title">{{ $nudge['title'] }}</p>
                                        <p class="biz-action-copy">{{ $nudge['copy'] }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="biz-button-row">
                        <a href="{{ $marketingUrl }}" class="biz-button">Ir a marketing</a>
                        <a href="{{ $teamUrl }}" class="biz-button-secondary">Ver equipo</a>
                        <a href="{{ $leadsUrl }}" class="biz-button-secondary">Revisar prospectos</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Madurez operativa</p>
                        <h2 class="biz-heading">{{ $maturityTitle }}</h2>
                        <p class="biz-muted">{{ $maturityCopy }}</p>
                    </div>

                    <div class="biz-stat">
                        <div class="biz-split">
                            <div>
                                <p class="biz-stat-label">Etapa actual</p>
                                <p class="biz-stat-value">{{ $maturityLabel }}</p>
                                <p class="biz-stat-copy">{{ $maturityNextStep }}</p>
                            </div>

                            <x-filament::badge color="{{ match ($maturityStage) {
                                'scale' => 'success',
                                'traction' => 'warning',
                                default => 'gray',
                            } }}">
                                {{ match ($maturityStage) {
                                    'scale' => 'Escala',
                                    'traction' => 'Traccion',
                                    default => 'Arranque',
                                } }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $teamUrl }}" class="biz-button">Ordenar equipo</a>
                        <a href="{{ $marketingUrl }}" class="biz-button-secondary">Activar seguimiento</a>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Equipo recomendado</p>
                        <h2 class="biz-heading">{{ $teamTitle }}</h2>
                        <p class="biz-muted">{{ $teamCopy }}</p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($teamPlaybooks as $member)
                            <a href="{{ $member['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-kicker">{{ $member['eyebrow'] }}</p>
                                        <p class="biz-action-title">{{ $member['title'] }}</p>
                                    </div>

                                    <x-filament::badge color="info">{{ $member['roleLabel'] }}</x-filament::badge>
                                </div>
                                <p class="biz-action-copy">{{ $member['copy'] }}</p>
                                @if ($member['when'] !== '')
                                    <p class="biz-muted">{{ $member['when'] }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Workspace inicial</p>
                        <h2 class="biz-heading">{{ $workspaceTitle }}</h2>
                        <p class="biz-muted">{{ $workspaceCopy }}</p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($workspaceModules as $module)
                            <a href="{{ $module['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-kicker">{{ $module['eyebrow'] }}</p>
                                        <p class="biz-action-title">{{ $module['title'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $module['stateColor'] }}">
                                        {{ $module['stateLabel'] }}
                                    </x-filament::badge>
                                </div>
                                <p class="biz-action-copy">{{ $module['copy'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Playbook operativo</p>
                        <h2 class="biz-heading">{{ $playbookTitle }}</h2>
                        <p class="biz-muted">{{ $playbookCopy }}</p>
                    </div>

                    <div class="biz-list">
                        @foreach ($segmentPlaybookSteps as $index => $step)
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-pending">{{ $index + 1 }}</div>
                                <div>
                                    <p class="biz-checklist-title">{{ $step['title'] }}</p>
                                    <p class="biz-checklist-copy">{{ $step['copy'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $publicUrl }}" target="_blank" class="biz-button">Ver mi micrositio</a>
                        <a href="{{ $profileUrl }}" class="biz-button-secondary">Ajustar perfil</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Automatizaciones comerciales</p>
                    <h2 class="biz-heading">{{ $automationTitle }}</h2>
                    <p class="biz-muted">{{ $automationCopy }}</p>
                </div>

                <div class="biz-actions">
                    @foreach ($automationPlaybooks as $automation)
                        <a href="{{ $automation['href'] }}" class="biz-action">
                            <div class="biz-split">
                                <div>
                                    <p class="biz-kicker">{{ $automation['eyebrow'] }}</p>
                                    <p class="biz-action-title">{{ $automation['title'] }}</p>
                                </div>

                                <x-filament::badge color="{{ $automation['statusColor'] }}">
                                    {{ $automation['statusLabel'] }}
                                </x-filament::badge>
                            </div>
                            <p class="biz-action-copy">{{ $automation['copy'] }}</p>
                            @if ($automation['trigger'] !== '')
                                <p class="biz-muted">{{ $automation['trigger'] }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
