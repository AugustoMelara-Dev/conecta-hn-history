<x-filament-widgets::widget>
    <div class="biz-card">
        <div class="biz-grid-two">
            <div class="biz-stack">
                <div class="biz-split">
                    <div>
                        <p class="biz-kicker">Activacion 7 dias</p>
                        <h3 class="biz-heading">Tu ruta de arranque en Vito Business OS</h3>
                        <p class="biz-kicker">{{ $businessLabel ?? 'Negocio' }} · Rol: {{ $roleLabel }} · Contexto: {{ $moduleLabel ?? 'General' }}</p>
                        <p class="biz-copy">{{ $nextAction['copy'] ?? 'Avanza con el siguiente paso recomendado.' }}</p>
                    </div>
                    <x-filament::badge color="{{ $progress >= 80 ? 'success' : ($progress >= 50 ? 'warning' : 'danger') }}">
                        {{ $progress }}%
                    </x-filament::badge>
                </div>

                <div class="biz-progress">
                    <div
                        class="biz-progress-bar {{ $progress < 50 ? 'biz-progress-bar-danger' : ($progress < 80 ? 'biz-progress-bar-warning' : '') }}"
                        style="width: {{ max(6, $progress) }}%;"
                    ></div>
                </div>

                <div class="biz-button-row">
                    <a href="{{ $nextAction['href'] ?? '#' }}" class="biz-button">
                        {{ $nextAction['title'] ?? 'Continuar activacion' }}
                    </a>
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
                    <div class="biz-checklist-item">
                        <div class="biz-checklist-index {{ $milestone['completed'] ? 'is-complete' : 'is-pending' }}">
                            {{ $milestone['completed'] ? 'OK' : $index + 1 }}
                        </div>
                        <div>
                            <p class="biz-checklist-title">{{ $milestone['title'] }}</p>
                            <p class="biz-checklist-copy">{{ $milestone['copy'] }}</p>
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
