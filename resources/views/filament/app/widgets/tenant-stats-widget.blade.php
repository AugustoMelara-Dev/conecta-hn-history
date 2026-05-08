<x-filament-widgets::widget>
    <x-filament::section data-testid="dashboard-tenant-stats-widget">
        <x-slot name="heading">
            Pulso del negocio
        </x-slot>

        <x-slot name="description">
            Lecturas rapidas de visibilidad, contacto y profundidad del catalogo.
        </x-slot>

        <div class="biz-stack">
            <div class="biz-metrics" data-testid="dashboard-traffic-metrics">
                <div class="biz-stat">
                    <div class="biz-split">
                        <div>
                            <p class="biz-stat-label">Visitas</p>
                            <p class="biz-stat-value">{{ number_format($views) }}</p>
                        </div>
                        <x-filament::badge color="info">Micrositio</x-filament::badge>
                    </div>
                    <p class="biz-stat-copy">Personas que han abierto la presencia publica del negocio.</p>
                </div>

                <div class="biz-stat">
                    <div class="biz-split">
                        <div>
                            <p class="biz-stat-label">Clics en WhatsApp</p>
                            <p class="biz-stat-value">{{ number_format($whatsappClicks) }}</p>
                        </div>
                        <x-filament::badge color="success">Contacto</x-filament::badge>
                    </div>
                    <p class="biz-stat-copy">
                        @if ($views > 0)
                            Tasa estimada: {{ $this->getConversionRate() }}% sobre visitas.
                        @else
                            Aparecera una tasa cuando ya existan visitas registradas.
                        @endif
                    </p>
                </div>

                <div class="biz-stat">
                    <div class="biz-split">
                        <div>
                            <p class="biz-stat-label">Productos activos</p>
                            <p class="biz-stat-value">{{ number_format($productsCount) }}</p>
                        </div>
                        <x-filament::badge color="{{ ! $isPro && $productsCount >= $productLimit ? 'warning' : 'gray' }}">
                            {{ $isPro ? 'Sin limite Semilla' : $productsCount . '/' . $productLimit }}
                        </x-filament::badge>
                    </div>
                    <p class="biz-stat-copy">Productos visibles para clientes en el catalogo.</p>
                </div>

                <div class="biz-stat" data-testid="dashboard-plan-state">
                    <div class="biz-split">
                        <div>
                            <p class="biz-stat-label">Plan actual</p>
                            <p class="biz-stat-value">{{ $planName }}</p>
                        </div>
                        <x-filament::badge color="{{ $isPro ? 'success' : 'gray' }}">
                            {{ $isPro ? 'Activo' : 'Semilla' }}
                        </x-filament::badge>
                    </div>
                    <p class="biz-stat-copy">
                        {{ $isPro ? 'Funciones PRO habilitadas para el negocio.' : 'Puedes operar con Semilla y revisar PRO cuando el catalogo lo pida.' }}
                    </p>
                </div>
            </div>

            @if (! $isPro && $productsCount >= $productLimit)
                <div class="biz-card-warning">
                    <div class="biz-split">
                        <div>
                            <p class="biz-action-title">Limite de productos alcanzado</p>
                            <p class="biz-muted">Antes de crear mas productos, revisa si conviene actualizar el plan o limpiar el catalogo.</p>
                        </div>

                        <x-filament::button
                            tag="a"
                            color="warning"
                            icon="heroicon-o-arrow-up-right"
                            href="{{ $this->getUpgradeUrl() }}"
                        >
                            Ver plan PRO
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
