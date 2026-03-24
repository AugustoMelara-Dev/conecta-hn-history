<x-filament-widgets::widget>
    <div class="biz-shell">
        <div class="biz-metrics">
            <div class="biz-stat">
                <p class="biz-stat-label">Visitas totales</p>
                <p class="biz-stat-value">{{ number_format($views) }}</p>
                <p class="biz-stat-copy">tráfico registrado hacia tu página.</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Clics en WhatsApp</p>
                <p class="biz-stat-value">{{ number_format($whatsappClicks) }}</p>
                <p class="biz-stat-copy">
                    @if ($views > 0)
                        Tasa de conversión aproximada: {{ $this->getConversionRate() }}%.
                    @else
                        Aún no hay suficiente tráfico para calcular conversión.
                    @endif
                </p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Productos activos</p>
                <p class="biz-stat-value">{{ number_format($productsCount) }}</p>
                <p class="biz-stat-copy">
                    @if (! $isPro)
                        {{ $productsCount }}/{{ $productLimit }} disponibles en plan Base.
                    @else
                        Catálogo sin límite operativo.
                    @endif
                </p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Tu suscripción</p>
                <p class="biz-stat-value">{{ $isPro ? 'PRO' : 'Base' }}</p>
                <p class="biz-stat-copy">
                    {{ $isPro ? 'Funciones premium activas.' : 'Modo esencial activo.' }}
                </p>
            </div>
        </div>

        <div class="{{ $isPro ? 'biz-card-accent' : 'biz-card-warning' }}">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge {{ $isPro ? 'biz-badge-success' : 'biz-badge-warning' }}">
                        {{ $planName }}
                    </div>

                    <div>
                        <h3 class="biz-heading">
                            {{ $isPro ? 'Tu negocio ya tiene todo desbloqueado' : 'Todavía puedes crecer más con PRO' }}
                        </h3>
                        <p class="biz-copy">
                            {{ $isPro
                                ? 'Sigue ampliando el catálogo y aprovecha las herramientas premium del panel.'
                                : 'Activa PRO para quitar límites del catálogo y habilitar herramientas adicionales del negocio.' }}
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Reseñas</p>
                            <p class="biz-stat-value">{{ number_format($reviewsCount) }}</p>
                            <p class="biz-stat-copy">
                                Calificación promedio: {{ number_format($rating, 1) }} / 5.
                            </p>
                        </div>

                        @if (! $isPro)
                            <div class="biz-button-row">
                                <a href="{{ $this->getUpgradeUrl() }}" class="biz-button">Activar plan PRO</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
