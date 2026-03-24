<x-filament-widgets::widget>
    <div class="biz-card">
        <div class="biz-stack">
            <div>
                <p class="biz-kicker">Audiencia</p>
                <h3 class="biz-heading">Seguidores registrados</h3>
            </div>

            <div class="biz-metrics">
                <div class="biz-stat">
                    <p class="biz-stat-label">Seguidores</p>
                    <p class="biz-stat-value">{{ number_format($count) }}</p>
                    <p class="biz-stat-copy">
                        {{ $isPro ? 'Listado desbloqueado para tu plan.' : 'Conteo general disponible en plan Base.' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
