<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Perfil del negocio
        </x-slot>

        <x-slot name="description">
            {{ $isComplete ? 'Tu negocio tiene la base lista para recibir clientes.' : 'Completa los elementos visibles que hacen que el negocio se vea confiable.' }}
        </x-slot>

        <div class="biz-stack" data-testid="account-completeness-widget">
            <div class="biz-split">
                <div>
                    <p class="biz-kicker">Progreso del perfil</p>
                    <p class="biz-stat-value">{{ $percentage }}%</p>
                    <p class="biz-muted">
                        {{ $isComplete ? 'Todo listo por ahora.' : 'Prioriza los pendientes que afectan la confianza del cliente.' }}
                    </p>
                </div>

                <x-filament::badge color="{{ $isComplete ? 'success' : ($percentage >= 60 ? 'warning' : 'gray') }}" size="lg">
                    {{ $isComplete ? 'Completo' : 'Pendiente' }}
                </x-filament::badge>
            </div>

            <div
                class="biz-progress"
                role="progressbar"
                aria-label="Progreso de perfil"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $percentage }}"
            >
                <div
                    class="biz-progress-bar {{ $percentage < 50 ? 'biz-progress-bar-danger' : ($percentage < 80 ? 'biz-progress-bar-warning' : '') }}"
                    style="width: {{ max(6, $percentage) }}%;"
                ></div>
            </div>

            @if (! $isComplete && count($missingItems) > 0)
                <div class="biz-actions">
                    @foreach (array_slice($missingItems, 0, 4) as $index => $item)
                        <a href="{{ $item['url'] }}" class="biz-action">
                            <div class="biz-checklist-item">
                                <div class="biz-checklist-index is-pending">{{ $index + 1 }}</div>
                                <div>
                                    <p class="biz-action-title">{{ $item['action'] }}</p>
                                    <p class="biz-action-copy">{{ $item['field'] }} mejora la presentacion publica del negocio.</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                @if (count($missingItems) > 4)
                    <p class="biz-muted">Quedan {{ count($missingItems) - 4 }} pendientes adicionales.</p>
                @endif
            @else
                <div class="biz-empty-state">
                    <p class="biz-action-title">Perfil completo</p>
                    <p class="biz-muted">Mantén la informacion actualizada cuando cambien horarios, productos o canales de contacto.</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
