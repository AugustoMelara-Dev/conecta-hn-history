<x-filament-widgets::widget>
    @php
        $wizardSteps = (array) ($wizard['steps'] ?? []);
        $currentWizardStep = collect($wizardSteps)->firstWhere('current', true);
        $wizardCompleted = (bool) ($wizard['completed'] ?? false);
        $wizardDismissed = (bool) ($wizard['dismissed'] ?? false);
        $wizardProgress = (int) ($wizard['progress'] ?? 0);
        $wizardHref = is_array($currentWizardStep) ? (string) ($currentWizardStep['href'] ?? '') : '';
    @endphp

    @if (! $wizardDismissed)
        <section class="biz-card" data-testid="first-run-wizard" aria-labelledby="first-run-title">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Configuracion guiada</p>
                        <h2 id="first-run-title" class="biz-heading">
                            {{ $wizardCompleted ? 'Tu base operativa esta lista' : 'Prepara tu negocio para recibir clientes' }}
                        </h2>
                        <p class="biz-copy">
                            {{ $wizardCompleted
                                ? 'Completaste los hitos verificables de esta ruta. El checklist seguira disponible abajo.'
                                : 'Cada paso se completa con datos reales del negocio; no necesitas marcar casillas manualmente.' }}
                        </p>
                    </div>
                    <span class="biz-kicker" role="status">
                        Progreso: {{ $wizardProgress }}%
                    </span>
                </div>

                <div
                    class="biz-progress"
                    role="progressbar"
                    aria-label="Progreso de configuracion guiada"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $wizardProgress }}"
                >
                    <div class="biz-progress-bar" style="width: {{ max(6, $wizardProgress) }}%;"></div>
                </div>

                <ol class="biz-list" aria-label="Pasos de configuracion">
                    @foreach ($wizardSteps as $index => $step)
                        @php
                            $stepStatus = (string) ($step['status'] ?? (($step['completed'] ?? false) ? 'ready' : 'pending'));
                            $stepBlocked = $stepStatus === 'blocked_by_plan';
                            $stepStatusLabel = $stepBlocked ? 'Bloqueado por plan' : (($step['completed'] ?? false) ? 'Listo' : 'Pendiente');
                        @endphp
                        <li
                            class="biz-checklist-item"
                            data-status="{{ $stepStatus }}"
                            @if (($step['current'] ?? false) === true) aria-current="step" @endif
                        >
                            <div class="biz-checklist-index {{ ($step['completed'] ?? false) ? 'is-complete' : ($stepBlocked ? 'is-blocked' : 'is-pending') }}" aria-hidden="true">
                                {{ ($step['completed'] ?? false) ? 'OK' : ($stepBlocked ? '!' : $index + 1) }}
                            </div>
                            <div>
                                <div class="biz-button-row" style="flex-wrap: wrap; min-width: 0;">
                                    <p class="biz-checklist-title">{{ $step['title'] ?? 'Paso de configuracion' }}</p>
                                    <span class="biz-kicker">{{ $stepStatusLabel }}</span>
                                </div>
                                <p class="biz-checklist-copy">{{ $step['copy'] ?? '' }}</p>
                                @if ($stepBlocked && filled($step['blockedReason'] ?? null))
                                    <p class="biz-checklist-copy" role="status">{{ $step['blockedReason'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="biz-button-row" style="flex-wrap: wrap; min-width: 0;">
                    @if ($wizardHref !== '' && $wizardHref !== '#')
                        <a href="{{ $wizardHref }}" class="biz-button" data-testid="first-run-open-step">
                            {{ $wizardCompleted ? 'Revisar configuracion' : 'Abrir siguiente paso' }}
                        </a>
                    @else
                        <span class="biz-button-secondary" role="link" aria-disabled="true" data-testid="first-run-open-step-disabled">
                            Ruta no disponible
                        </span>
                    @endif

                    @if (! $wizardCompleted)
                        <button
                            type="button"
                            class="biz-button-secondary"
                            wire:click="dismissFirstRunWizard"
                            wire:loading.attr="disabled"
                            data-testid="first-run-dismiss"
                        >
                            Verlo despues
                        </button>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <div class="biz-card" data-testid="launch-readiness-card">
        <div class="biz-grid-two">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Activacion 7 dias</p>
                        <h3 class="biz-heading">Tu ruta de arranque en Dovra AI</h3>
                        <p class="biz-kicker">{{ $businessLabel ?? 'Negocio' }} - Rol: {{ $roleLabel }} - Contexto: {{ $moduleLabel ?? 'General' }}</p>
                        <p class="biz-copy">{{ $nextAction['copy'] ?? 'Avanza con el siguiente paso recomendado.' }}</p>
                    </div>
                    <span class="biz-kicker" role="status">
                        Progreso: {{ $progress }}%
                    </span>
                </div>

                <div
                    class="biz-progress"
                    role="progressbar"
                    aria-label="Progreso de activacion"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $progress }}"
                >
                    <div
                        class="biz-progress-bar {{ $progress < 50 ? 'biz-progress-bar-danger' : ($progress < 80 ? 'biz-progress-bar-warning' : '') }}"
                        style="width: {{ max(6, $progress) }}%;"
                    ></div>
                </div>

                @php
                    $nextHref = $nextAction['href'] ?? '';
                @endphp

                <div class="biz-button-row" style="flex-wrap: wrap; min-width: 0;">
                    @if ($nextHref !== '' && $nextHref !== '#')
                        <a href="{{ $nextHref }}" class="biz-button">
                            {{ $nextAction['title'] ?? 'Continuar activacion' }}
                        </a>
                    @else
                        <span class="biz-button-secondary" role="link" aria-disabled="true">
                            {{ $nextAction['title'] ?? 'Continuar activacion' }}
                        </span>
                    @endif
                </div>

                @if (! empty($supportTips))
                    <div class="biz-list">
                        @foreach ($supportTips as $tip)
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-pending">?</div>
                                <div>
                                    <p class="biz-checklist-title">{{ $tip['title'] ?? 'Guia rapida' }}</p>
                                    <p class="biz-checklist-copy">{{ $tip['copy'] ?? '' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="biz-list">
                @forelse ($milestones as $index => $milestone)
                    @php
                        $milestoneStatus = (string) ($milestone['status'] ?? ($milestone['completed'] ? 'ready' : 'pending'));
                        $milestoneBlocked = $milestoneStatus === 'blocked_by_plan';
                        $milestoneStatusLabel = $milestoneBlocked ? 'Bloqueado por plan' : ($milestone['completed'] ? 'Listo' : 'Pendiente');
                    @endphp
                    <div class="biz-checklist-item" data-testid="readiness-status-{{ $milestoneStatus }}" data-status="{{ $milestoneStatus }}">
                        <div class="biz-checklist-index {{ $milestone['completed'] ? 'is-complete' : ($milestoneBlocked ? 'is-blocked' : 'is-pending') }}" aria-hidden="true">
                            {{ $milestone['completed'] ? 'OK' : ($milestoneBlocked ? '!' : $index + 1) }}
                        </div>
                        <div>
                            <div class="biz-button-row" style="flex-wrap: wrap; min-width: 0;">
                                <p class="biz-checklist-title">{{ $milestone['title'] }}</p>
                                <span class="biz-kicker">{{ $milestoneStatusLabel }}</span>
                            </div>
                            <p class="biz-checklist-copy">{{ $milestone['copy'] }}</p>
                            @if ($milestoneBlocked && filled($milestone['blockedReason'] ?? null))
                                <p class="biz-checklist-copy" role="status">{{ $milestone['blockedReason'] }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="biz-empty-state">
                        <p class="biz-muted">No hay hitos por mostrar en este momento.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
