<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card-accent">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-success">Superadmin</div>

                    <div>
                        <p class="biz-kicker">Torre de control multi-negocio</p>
                        <h1 class="biz-title">Colas, SLA, churn y seguimiento entre tenants</h1>
                        <p class="biz-copy">
                            Opera Conecta HN como una consola de ejecucion real: pagos, altas,
                            playbooks comerciales, renovaciones y riesgo de churn en un solo frente.
                        </p>
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $operationsUrl }}" class="biz-button">Mesa operativa</a>
                        <a href="{{ $tenantsUrl }}" class="biz-button">Ver negocios</a>
                        <a href="{{ $paymentsUrl }}" class="biz-button-secondary">Revisar pagos</a>
                        <a href="{{ $billingUrl }}" class="biz-button-secondary">Facturacion</a>
                        <a href="{{ $settingsUrl }}" class="biz-button-secondary">Ajustar sistema</a>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-stat-label">Pulso de hoy</p>
                            <p class="biz-stat-value">{{ number_format($openAttentionCount) }}</p>
                            <p class="biz-stat-copy">
                                frentes abiertos entre pagos, activaciones, churn y operaciones vivas.
                            </p>
                        </div>

                        <div class="biz-metrics">
                            <div class="biz-stat">
                                <p class="biz-stat-label">Playbooks activos</p>
                                <p class="biz-stat-value">{{ number_format($activePlaybookCount) }}</p>
                                <p class="biz-stat-copy">Seguimientos vivos entre tenants</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-stat-label">SLA vencido</p>
                                <p class="biz-stat-value">{{ number_format($overduePlaybookCount) }}</p>
                                <p class="biz-stat-copy">Casos que ya piden escalacion</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-stat-label">Sin owner interno</p>
                                <p class="biz-stat-value">{{ number_format($unassignedPlaybookCount) }}</p>
                                <p class="biz-stat-copy">Casos que todavia no tienen duenio comercial interno</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-metrics">
            <div class="biz-stat">
                <p class="biz-stat-label">Negocios</p>
                <p class="biz-stat-value">{{ number_format($tenantsCount) }}</p>
                <p class="biz-stat-copy">Base total en la plataforma</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">PRO activos</p>
                <p class="biz-stat-value">{{ number_format($proTenantsCount) }}</p>
                <p class="biz-stat-copy">Cuentas premium activas</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">PRO este mes</p>
                <p class="biz-stat-value">{{ number_format($proConversionsThisMonthCount) }}</p>
                <p class="biz-stat-copy">Conversiones reales cerradas en el mes actual</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Usuarios</p>
                <p class="biz-stat-value">{{ number_format($usersCount) }}</p>
                <p class="biz-stat-copy">Cuentas creadas</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Churn watch</p>
                <p class="biz-stat-value">{{ number_format($crmWatchlistCount) }}</p>
                <p class="biz-stat-copy">Cuentas persistidas en watchlist</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Cartera vencida</p>
                <p class="biz-stat-value">{{ number_format($crmOverdueAccountsCount) }}</p>
                <p class="biz-stat-copy">Toques atrasados a nivel de cuenta</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Owners activos</p>
                <p class="biz-stat-value">{{ number_format($crmOwnedAccountsCount) }}</p>
                <p class="biz-stat-copy">Cuentas con responsable de cartera</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Toques de hoy</p>
                <p class="biz-stat-value">{{ number_format($crmFollowupsTodayCount) }}</p>
                <p class="biz-stat-copy">Seguimientos agendados para hoy</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Activacion automatica</p>
                <p class="biz-stat-value">{{ number_format($activationPlaybookCount) }}</p>
                <p class="biz-stat-copy">Cuentas nuevas que todavia piden empuje comercial</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Expansion automatica</p>
                <p class="biz-stat-value">{{ number_format($growthPlaybookCount) }}</p>
                <p class="biz-stat-copy">Negocios listos para upgrade, growth o plan PRO</p>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Colas globales</p>
                        <h2 class="biz-heading">Colas globales por impacto</h2>
                        <p class="biz-muted">
                            La vista ejecutiva para decidir donde invertir soporte, operaciones y seguimiento.
                        </p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($attentionQueues as $queue)
                            <a href="{{ $queue['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $queue['label'] }}</p>
                                        <p class="biz-action-copy">{{ $queue['copy'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $queue['tone'] }}">
                                        {{ number_format($queue['count']) }}
                                    </x-filament::badge>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">SLA por equipo</p>
                        <h2 class="biz-heading">Equipos operativos y riesgo de ejecucion</h2>
                        <p class="biz-muted">
                            Una lectura rapida por lane para saber donde falta respuesta, owner interno o disciplina.
                        </p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($operationalLanes as $lane)
                            <a href="{{ $lane['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $lane['label'] }}</p>
                                        <p class="biz-action-copy">{{ $lane['copy'] }}</p>
                                        <p class="biz-muted">Vencidos: {{ $lane['overdue'] }} | Sin owner: {{ $lane['unassigned'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $lane['tone'] }}">
                                        {{ number_format($lane['count']) }}
                                    </x-filament::badge>
                                </div>
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
                        <p class="biz-kicker">Workboard</p>
                        <h2 class="biz-heading">Casos calientes entre tenants</h2>
                        <p class="biz-muted">
                            Los frentes operativos que merecen tocarse primero por impacto comercial o riesgo de SLA.
                        </p>
                    </div>

                    @if ($globalWorkboard !== [])
                        <div class="biz-actions">
                            @foreach ($globalWorkboard as $task)
                                <a href="{{ $task['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-kicker">{{ $task['queue'] }}</p>
                                            <p class="biz-action-title">{{ $task['task'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $task['tone'] }}">
                                            {{ $task['sla'] }}
                                        </x-filament::badge>
                                    </div>

                                    <p class="biz-action-copy">{{ $task['business'] }}</p>
                                    <p class="biz-muted">{{ $task['owner'] }} | Owner interno: {{ $task['internal_owner'] }}</p>
                                    <p class="biz-muted">Seguimiento: {{ $task['follow_up'] }} | {{ $task['next_touch'] }}</p>
                                    <p class="biz-muted">{{ $task['assignee'] }}</p>
                                    <p class="biz-note">{{ $task['reason'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Operacion',
                            'heading' => 'Todavia no hay casos calientes',
                            'description' => 'Cuando los tenants disparen leads, citas, recompras o renovaciones, caeran aqui con SLA.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Responsables</p>
                        <h2 class="biz-heading">Owners internos y carga operativa</h2>
                        <p class="biz-muted">
                            Detecta rapidamente quien esta cargando mas ejecucion y donde faltan owners claros.
                        </p>
                    </div>

                    @if ($assigneeLoad !== [])
                        <div class="biz-actions">
                            @foreach ($assigneeLoad as $assignee)
                                <a href="{{ $assignee['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $assignee['name'] }}</p>
                                            <p class="biz-action-copy">{{ $assignee['context'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $assignee['tone'] }}">
                                            {{ $assignee['task_count'] }} activos
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">SLA vencido: {{ $assignee['overdue_count'] }} | Toques hoy: {{ $assignee['next_touch_count'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Equipo',
                            'heading' => 'Todavia no hay responsables cargados',
                            'description' => 'Cuando los playbooks empiecen a repartirse entre equipos, veras la carga aqui.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Owner view</p>
                        <h2 class="biz-heading">Vista por owner</h2>
                        <p class="biz-muted">
                            Lectura de cartera por responsable interno para repartir carga, riesgo y renovaciones.
                        </p>
                    </div>

                    @if ($ownerPortfolio !== [])
                        <div class="biz-actions">
                            @foreach ($ownerPortfolio as $owner)
                                <a href="{{ $owner['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $owner['name'] }}</p>
                                            <p class="biz-action-copy">{{ $owner['context'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $owner['tone'] }}">
                                            {{ $owner['account_count'] }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Watchlist: {{ $owner['watch_count'] }} | Renovacion: {{ $owner['renewal_count'] }} | SLA vencido: {{ $owner['overdue_count'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Owner',
                            'heading' => 'Todavia no hay cartera por owner',
                            'description' => 'Cuando soporte comercial asigne responsables, el tablero mostrara la carga y el riesgo por owner.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Team view</p>
                        <h2 class="biz-heading">Vista por equipo</h2>
                        <p class="biz-muted">
                            Activacion, growth, cobros, retencion y soporte con lectura propia de backlog y watchlist.
                        </p>
                    </div>

                    @if ($teamPortfolio !== [])
                        <div class="biz-actions">
                            @foreach ($teamPortfolio as $team)
                                <a href="{{ $team['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $team['team'] }}</p>
                                            <p class="biz-action-copy">{{ $team['context'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $team['tone'] }}">
                                            {{ $team['account_count'] }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Watchlist: {{ $team['watch_count'] }} | SLA vencido: {{ $team['overdue_count'] }} | Toques hoy: {{ $team['next_touch_count'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Equipo',
                            'heading' => 'Todavia no hay lectura por equipo',
                            'description' => 'Cuando el CRM interno empiece a poblar cartera por area, veras el backlog de activacion, growth, cobros y retencion.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Backlog</p>
                        <h2 class="biz-heading">Cartera vencida por SLA</h2>
                        <p class="biz-muted">
                            Cuentas con siguiente toque atrasado o alertas altas que ya exigen movimiento comercial concreto.
                        </p>
                    </div>

                    @if ($slaBacklog !== [])
                        <div class="biz-actions">
                            @foreach ($slaBacklog as $account)
                                <a href="{{ $account['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $account['business'] }}</p>
                                            <p class="biz-action-copy">{{ $account['owner'] }} | {{ $account['team'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $account['tone'] }}">
                                            {{ $account['watch'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">{{ $account['status'] }} | {{ $account['next_touch'] }}</p>
                                    <p class="biz-note">{{ $account['backlog'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'SLA',
                            'heading' => 'La cartera esta al dia',
                            'description' => 'Cuando un toque se atrase o una cuenta de alto riesgo quede sin movimiento, caera en este backlog.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Embudo</p>
                        <h2 class="biz-heading">Embudo comercial entre tenants</h2>
                        <p class="biz-muted">
                            Lectura de red para entender donde esta el portfolio: alta, onboarding, operacion, upsell, renovacion o riesgo.
                        </p>
                    </div>

                    <div class="biz-actions">
                        @foreach ($commercialFunnel as $stage)
                            <a href="{{ $stage['href'] }}" class="biz-action">
                                <div class="biz-split">
                                    <div>
                                        <p class="biz-action-title">{{ $stage['stage'] }}</p>
                                        <p class="biz-action-copy">{{ $stage['copy'] }}</p>
                                    </div>

                                    <x-filament::badge color="{{ $stage['tone'] }}">
                                        {{ number_format($stage['count']) }}
                                    </x-filament::badge>
                                </div>
                                <p class="biz-muted">{{ $stage['share'] }} de la base total</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Churn</p>
                        <h2 class="biz-heading">Riesgo de churn</h2>
                        <p class="biz-muted">
                            Cuentas que pueden enfriarse, cortarse o caer si soporte y comercial no actuan a tiempo.
                        </p>
                    </div>

                    @if ($churnWatchlist !== [])
                        <div class="biz-actions">
                            @foreach ($churnWatchlist as $tenant)
                                <a href="{{ $tenant['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $tenant['business'] }}</p>
                                            <p class="biz-action-copy">{{ $tenant['owner'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $tenant['tone'] }}">
                                            {{ $tenant['metric'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Owner interno: {{ $tenant['internal_owner'] }} | {{ $tenant['flagged_at'] }}</p>
                                    <p class="biz-note">{{ $tenant['reason'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Churn',
                            'heading' => 'No hay alertas fuertes ahora mismo',
                            'description' => 'Cuando una cuenta premium, inactiva o suspendida suba de riesgo, la veras aqui.',
                            'buttonLabel' => 'Ver facturacion',
                            'buttonUrl' => $billingUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Follow-up</p>
                        <h2 class="biz-heading">Seguimiento comercial entre tenants</h2>
                        <p class="biz-muted">
                            La cartera viva de leads, abandonos y recompra que hoy puede mover ingresos para los negocios.
                        </p>
                    </div>

                    @if ($commercialFollowups !== [])
                        <div class="biz-actions">
                            @foreach ($commercialFollowups as $followup)
                                <a href="{{ $followup['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-kicker">{{ $followup['queue'] }}</p>
                                            <p class="biz-action-title">{{ $followup['playbook'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $followup['tone'] }}">
                                            {{ $followup['sla'] }}
                                        </x-filament::badge>
                                    </div>

                                    <p class="biz-action-copy">{{ $followup['business'] }}</p>
                                    <p class="biz-muted">Owner interno: {{ $followup['owner'] }}</p>
                                    <p class="biz-muted">{{ $followup['follow_up'] }} | {{ $followup['next_touch'] }}</p>
                                    <p class="biz-muted">{{ $followup['assignee'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Comercial',
                            'heading' => 'Todavia no hay follow-up activo',
                            'description' => 'Cuando los tenants generen leads o recompras, apareceran aqui con prioridad y responsable.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Metas mensuales por vertical</p>
                        <h2 class="biz-heading">Metas mensuales por vertical</h2>
                        <p class="biz-muted">
                            Cruza target, pipeline y conversiones PRO reales para saber donde crecer con mas precision.
                        </p>
                    </div>

                    @if ($verticalMonthlyGoals !== [])
                        <div class="biz-actions">
                            @foreach ($verticalMonthlyGoals as $vertical)
                                <a href="{{ $vertical['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $vertical['vertical'] }}</p>
                                            <p class="biz-action-copy">{{ $vertical['focus'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $vertical['tone'] }}">
                                            {{ $vertical['attainment'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Meta: {{ $vertical['target'] }} | PRO este mes: {{ $vertical['converted'] }} | Pipeline: {{ $vertical['pipeline'] }}</p>
                                    <p class="biz-note">Base PRO actual: {{ $vertical['pro'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Verticales',
                            'heading' => 'Todavia no hay metas por vertical',
                            'description' => 'Cuando la red gane volumen por segmento, aqui veras targets reales contra conversiones PRO del mes.',
                            'buttonLabel' => 'Abrir facturacion',
                            'buttonUrl' => $billingUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Cohortes por plan</p>
                        <h2 class="biz-heading">Cohortes por plan</h2>
                        <p class="biz-muted">
                            Mira que planes ya tienen expansion lista, cuales concentran watchlist y donde ya hubo cierres PRO.
                        </p>
                    </div>

                    @if ($planCohorts !== [])
                        <div class="biz-actions">
                            @foreach ($planCohorts as $cohort)
                                <a href="{{ $cohort['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $cohort['plan'] }}</p>
                                            <p class="biz-action-copy">PRO este mes: {{ $cohort['converted'] }} | PRO activos: {{ $cohort['pro'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $cohort['tone'] }}">
                                            {{ number_format($cohort['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Expansion: {{ $cohort['expansion'] }} | Watchlist: {{ $cohort['watch'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Planes',
                            'heading' => 'Todavia no hay cohortes por plan',
                            'description' => 'Cuando la base tenga mas mezcla de planes y upgrades, aqui veras el movimiento real por cartera.',
                            'buttonLabel' => 'Abrir facturacion',
                            'buttonUrl' => $billingUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Revenue ops</p>
                        <h2 class="biz-heading">Cohortes de activacion</h2>
                        <p class="biz-muted">
                            Mide si las cuentas nuevas realmente logran pasar de alta a operacion y PRO.
                        </p>
                    </div>

                    @if ($activationCohorts !== [])
                        <div class="biz-table">
                            <table class="biz-table-grid">
                                <thead>
                                    <tr class="biz-table-head-row">
                                        <th class="biz-table-head-cell">Cohorte</th>
                                        <th class="biz-table-head-cell">Base</th>
                                        <th class="biz-table-head-cell">Activacion</th>
                                        <th class="biz-table-head-cell">Onboarding</th>
                                        <th class="biz-table-head-cell">PRO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activationCohorts as $cohort)
                                        <tr class="biz-table-row">
                                            <td class="biz-table-cell">
                                                <a href="{{ $cohort['href'] }}" class="biz-table-cell-strong">{{ $cohort['label'] }}</a>
                                            </td>
                                            <td class="biz-table-cell">{{ number_format($cohort['count']) }}</td>
                                            <td class="biz-table-cell">
                                                <x-filament::badge color="{{ (int) $cohort['active'] > 0 ? 'success' : 'gray' }}">
                                                    {{ $cohort['activation_rate'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="biz-table-cell">{{ number_format($cohort['onboarding']) }}</td>
                                            <td class="biz-table-cell">{{ number_format($cohort['pro']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Revenue ops',
                            'heading' => 'Todavia no hay cohortes visibles',
                            'description' => 'Cuando entre mas base, aqui veras si activacion y upgrade realmente estan avanzando.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Expansion</p>
                        <h2 class="biz-heading">Expansion por segmento</h2>
                        <p class="biz-muted">
                            Detecta donde la red ya tiene base lista para mover plan PRO, servicios premium o playbooks de growth.
                        </p>
                    </div>

                    @if ($segmentExpansion !== [])
                        <div class="biz-actions">
                            @foreach ($segmentExpansion as $segment)
                                <a href="{{ $segment['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $segment['segment'] }}</p>
                                            <p class="biz-action-copy">{{ $segment['coverage'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $segment['tone'] }}">
                                            {{ number_format($segment['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Expansion: {{ $segment['expansion'] }} | Activacion: {{ $segment['activation'] }} | PRO: {{ $segment['pro'] }}</p>
                                    <p class="biz-muted">PRO este mes: {{ $segment['converted'] }}</p>
                                    <p class="biz-note">Watchlist: {{ $segment['watch'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Expansion',
                            'heading' => 'Todavia no hay segmentos listos para crecer',
                            'description' => 'Cuando la red tenga base con oferta, canal de cierre y contexto comercial, aqui veras la mejor expansion por vertical.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card-warning">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Churn por vertical</p>
                        <h2 class="biz-heading">Churn por vertical</h2>
                        <p class="biz-muted">
                            Prioriza rescate por segmento para no perder cuentas donde la red ya mostro potencial.
                        </p>
                    </div>

                    @if ($verticalChurn !== [])
                        <div class="biz-actions">
                            @foreach ($verticalChurn as $vertical)
                                <a href="{{ $vertical['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $vertical['vertical'] }}</p>
                                            <p class="biz-action-copy">{{ $vertical['watch'] }} cuentas en watchlist</p>
                                        </div>

                                        <x-filament::badge color="{{ $vertical['tone'] }}">
                                            {{ number_format($vertical['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Criticas: {{ $vertical['critical'] }} | Suspendidas: {{ $vertical['suspended'] }} | Renovacion: {{ $vertical['renewal'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Churn',
                            'heading' => 'Todavia no hay verticales en observacion',
                            'description' => 'Cuando un segmento concentre cuentas frias, vencidas o suspendidas, aparecera aqui con contexto comercial.',
                            'buttonLabel' => 'Abrir facturacion',
                            'buttonUrl' => $billingUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Playbooks</p>
                        <h2 class="biz-heading">Playbooks revenue ops</h2>
                        <p class="biz-muted">
                            La mesa viva de activacion, expansion y renovacion para operar la red como un revenue engine.
                        </p>
                    </div>

                    @if ($revenuePlaybooks !== [])
                        <div class="biz-actions">
                            @foreach ($revenuePlaybooks as $playbook)
                                <a href="{{ $playbook['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-kicker">{{ $playbook['queue'] }}</p>
                                            <p class="biz-action-title">{{ $playbook['playbook'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $playbook['tone'] }}">
                                            {{ $playbook['sla'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-action-copy">{{ $playbook['business'] }}</p>
                                    <p class="biz-muted">{{ $playbook['segment'] }} | Owner interno: {{ $playbook['owner'] }}</p>
                                    <p class="biz-muted">{{ $playbook['follow_up'] }} | {{ $playbook['next_touch'] }}</p>
                                    <p class="biz-note">{{ $playbook['conversion_signal'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Playbooks',
                            'heading' => 'Todavia no hay revenue ops activo',
                            'description' => 'Cuando activacion, expansion o renovacion automaticas disparen seguimiento, todo caera en esta cola.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Metas por owner</p>
                        <h2 class="biz-heading">Metas por owner</h2>
                        <p class="biz-muted">
                            Que tan bien esta cubierta la cartera mas sensible por cada responsable interno.
                        </p>
                    </div>

                    @if ($ownerGoals !== [])
                        <div class="biz-actions">
                            @foreach ($ownerGoals as $goal)
                                <a href="{{ $goal['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $goal['owner'] }}</p>
                                            <p class="biz-action-copy">{{ $goal['mix'] !== '' ? $goal['mix'] : 'Sin mix de revenue pendiente' }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $goal['tone'] }}">
                                            {{ $goal['coverage'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Cuentas: {{ $goal['accounts'] }} | Meta: {{ $goal['goal'] }} | Cubierto: {{ $goal['covered'] }} | Gap: {{ $goal['gap'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Owners',
                            'heading' => 'Todavia no hay metas por owner',
                            'description' => 'Cuando la red tenga cartera activa con motions reales, aqui veras la cobertura por responsable.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif

                    <div>
                        <p class="biz-kicker">Cohortes por owner</p>
                        <h3 class="biz-heading">Cohortes por owner</h3>
                        <p class="biz-muted">
                            Cruza cartera, expansion y cierres PRO del mes para ver quien esta convirtiendo de verdad.
                        </p>
                    </div>

                    @if ($ownerCohorts !== [])
                        <div class="biz-actions">
                            @foreach ($ownerCohorts as $cohort)
                                <a href="{{ $cohort['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $cohort['owner'] }}</p>
                                            <p class="biz-action-copy">PRO este mes: {{ $cohort['converted'] }} | PRO activos: {{ $cohort['pro'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $cohort['tone'] }}">
                                            {{ number_format($cohort['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Expansion: {{ $cohort['expansion'] }} | Watchlist: {{ $cohort['watch'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Owner',
                            'heading' => 'Todavia no hay cohortes por owner',
                            'description' => 'Cuando soporte comercial cierre conversiones y cartera por responsable, veras la lectura aqui.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Metas por equipo</p>
                        <h2 class="biz-heading">Metas por equipo</h2>
                        <p class="biz-muted">
                            La lectura de target, cobertura y gap para activacion, growth, cobros y retencion.
                        </p>
                    </div>

                    @if ($teamGoals !== [])
                        <div class="biz-actions">
                            @foreach ($teamGoals as $team)
                                <a href="{{ $team['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $team['team'] }}</p>
                                            <p class="biz-action-copy">{{ $team['focus'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $team['tone'] }}">
                                            Gap {{ $team['gap'] }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Cartera: {{ $team['portfolio'] }} | Meta: {{ $team['goal'] }} | Cubierto: {{ $team['covered'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Equipos',
                            'heading' => 'Todavia no hay metas por equipo',
                            'description' => 'Cuando activacion, growth, cobros o retencion tengan volumen, este tablero mostrara target y cobertura.',
                            'buttonLabel' => 'Abrir mesa operativa',
                            'buttonUrl' => $operationsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Ciudades</p>
                        <h2 class="biz-heading">Cohortes por ciudad</h2>
                        <p class="biz-muted">
                            Donde la red esta activando mejor, donde ya hay expansion y donde conviene intervenir antes.
                        </p>
                    </div>

                    @if ($cityCohorts !== [])
                        <div class="biz-actions">
                            @foreach ($cityCohorts as $cohort)
                                <a href="{{ $cohort['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $cohort['city'] }}</p>
                                            <p class="biz-action-copy">Activacion {{ $cohort['activation_rate'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $cohort['tone'] }}">
                                            {{ number_format($cohort['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Expansion: {{ $cohort['expansion'] }} | PRO: {{ $cohort['pro'] }} | PRO este mes: {{ $cohort['converted'] }}</p>
                                    <p class="biz-note">Watchlist: {{ $cohort['watch'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Ciudades',
                            'heading' => 'Todavia no hay cohortes por ciudad',
                            'description' => 'Cuando la red gane base geografica, aqui veras que plazas activan y escalan mejor.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Canales</p>
                        <h2 class="biz-heading">Cohortes por canal</h2>
                        <p class="biz-muted">
                            Lectura por canal de cierre real para entender donde hay traccion y donde falta puente comercial.
                        </p>
                    </div>

                    @if ($channelCohorts !== [])
                        <div class="biz-actions">
                            @foreach ($channelCohorts as $cohort)
                                <a href="{{ $cohort['href'] }}" class="biz-action">
                                    <div class="biz-split">
                                        <div>
                                            <p class="biz-action-title">{{ $cohort['channel'] }}</p>
                                            <p class="biz-action-copy">{{ $cohort['copy'] }}</p>
                                        </div>

                                        <x-filament::badge color="{{ $cohort['tone'] }}">
                                            {{ number_format($cohort['count']) }} cuentas
                                        </x-filament::badge>
                                    </div>
                                    <p class="biz-muted">Activacion: {{ $cohort['activation_rate'] }} | Expansion: {{ $cohort['expansion'] }} | PRO: {{ $cohort['pro'] }} | Watchlist: {{ $cohort['watch'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Canales',
                            'heading' => 'Todavia no hay cohortes por canal',
                            'description' => 'Cuando los negocios dejen mas puentes de cierre claros, aqui veras el impacto por canal.',
                            'buttonLabel' => 'Abrir negocios',
                            'buttonUrl' => $tenantsUrl,
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Finanzas</p>
                        <h2 class="biz-heading">Ultimos movimientos reportados</h2>
                        <p class="biz-muted">
                            Una lectura rapida para saber si soporte financiero va al dia.
                        </p>
                    </div>

                    @if ($recentPayments !== [])
                        <div class="biz-table">
                            <table class="biz-table-grid">
                                <thead>
                                    <tr class="biz-table-head-row">
                                        <th class="biz-table-head-cell">Negocio</th>
                                        <th class="biz-table-head-cell">Referencia</th>
                                        <th class="biz-table-head-cell">Estado</th>
                                        <th class="biz-table-head-cell">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentPayments as $payment)
                                        <tr class="biz-table-row">
                                            <td class="biz-table-cell">
                                                <p class="biz-table-cell-strong">{{ $payment['business'] }}</p>
                                                <p class="biz-table-cell-muted">{{ $payment['reported_at'] }}</p>
                                            </td>
                                            <td class="biz-table-cell biz-table-cell-mono">{{ $payment['reference'] }}</td>
                                            <td class="biz-table-cell">
                                                <x-filament::badge color="{{ $payment['tone'] }}">
                                                    {{ $payment['status'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="biz-table-cell biz-table-cell-strong">{{ $payment['amount'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'badge' => 'Pagos',
                            'heading' => 'Todavia no hay pagos reportados',
                            'description' => 'Cuando entren comprobantes o revisiones, apareceran en este tablero.',
                            'buttonLabel' => 'Revisar pagos',
                            'buttonUrl' => $paymentsUrl,
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Accesos rapidos</p>
                        <h2 class="biz-heading">Frentes de soporte y gobierno</h2>
                        <p class="biz-muted">
                            Atajos para actuar rapido cuando un caso necesita entrar a negocio, pagos o sistema.
                        </p>
                    </div>

                    <div class="biz-grid-auto">
                        <a href="{{ $tenantsUrl }}" class="biz-action">
                            <p class="biz-action-title">Administrar negocios</p>
                            <p class="biz-action-copy">Alta, soporte, suspension y contexto operativo de cada cuenta.</p>
                        </a>

                        <a href="{{ $paymentsUrl }}" class="biz-action">
                            <p class="biz-action-title">Centro de pagos</p>
                            <p class="biz-action-copy">Aprueba comprobantes y acelera activaciones PRO.</p>
                        </a>

                        <a href="{{ $operationsUrl }}" class="biz-action">
                            <p class="biz-action-title">Mesa operativa</p>
                            <p class="biz-action-copy">Owners internos, notas, SLA y siguiente toque entre tenants.</p>
                        </a>

                        <a href="{{ $usersUrl }}" class="biz-action">
                            <p class="biz-action-title">Usuarios y acceso</p>
                            <p class="biz-action-copy">Bans, contrasenas, soporte e impersonacion.</p>
                        </a>

                        <a href="{{ $settingsUrl }}" class="biz-action">
                            <p class="biz-action-title">Configuracion global</p>
                            <p class="biz-action-copy">Precio PRO, soporte y reglas centrales del sistema.</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
