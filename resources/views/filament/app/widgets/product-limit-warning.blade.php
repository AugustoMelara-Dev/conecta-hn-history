<x-filament-widgets::widget>
    <div class="biz-card-warning">
        <div class="biz-grid-two">
            <div class="biz-stack">
                <div class="biz-badge biz-badge-warning">
                    @if (! $canCreate)
                        Límite alcanzado
                    @else
                        Capacidad casi llena
                    @endif
                </div>

                <div>
                    <h3 class="biz-heading">Catálogo del plan Base</h3>
                    <p class="biz-copy">
                        Tienes <strong>{{ $count }}/{{ $limit }}</strong> productos registrados.
                        @if (! $canCreate)
                            Necesitas PRO para seguir agregando.
                        @else
                            Todavía puedes crear algunos más, pero ya estás cerca del tope.
                        @endif
                    </p>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Uso actual</p>
                        <p class="biz-stat-value">{{ min($percentage, 100) }}%</p>
                        <p class="biz-stat-copy">del espacio disponible en tu plan.</p>
                    </div>

                    <div class="biz-progress">
                        <div
                            class="biz-progress-bar {{ $percentage >= 100 ? 'biz-progress-bar-danger' : 'biz-progress-bar-warning' }}"
                            style="width: {{ min($percentage, 100) }}%;"
                        ></div>
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $upgradeUrl }}" class="biz-button">Mejorar a PRO</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
