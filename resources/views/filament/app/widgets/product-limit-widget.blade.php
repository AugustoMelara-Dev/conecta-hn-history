<x-filament-widgets::widget>
    <x-filament::section data-testid="dashboard-product-limit-widget">
        <x-slot name="heading">
            Uso del catalogo
        </x-slot>

        <x-slot name="description">
            Free valida tu negocio con hasta 10 productos. PRO amplia catalogo, reservas/reportes, campanas e IA operativa activable.
        </x-slot>

        <div class="biz-stack" data-testid="dashboard-product-usage">
            @if ($this->isPro)
                <div class="biz-split">
                    <div class="biz-stack">
                        <div class="biz-button-row" data-testid="dashboard-plan-state">
                            <x-filament::badge color="success" size="lg">Plan PRO</x-filament::badge>
                            <x-filament::badge color="gray" size="lg">Catalogo ampliado</x-filament::badge>
                        </div>

                        <div>
                            <p class="biz-kicker">Estado del plan</p>
                            <p class="biz-heading">Tu catalogo tiene capacidad ampliada frente al limite Free.</p>
                            <p class="biz-muted">
                                Puedes seguir administrando productos y revisar reservas, reportes, campanas e IA segun las capacidades activadas.
                            </p>
                        </div>

                        @if ($this->planExpiresAt)
                            <p class="biz-note">Proxima renovacion: {{ $this->planExpiresAt }}</p>
                        @endif
                    </div>

                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-credit-card"
                        href="{{ $this->billingUrl }}"
                    >
                        Gestionar facturacion
                    </x-filament::button>
                </div>
            @else
                <div class="biz-split">
                    <div class="biz-stack">
                        <div class="biz-button-row" data-testid="dashboard-plan-state">
                            <x-filament::badge color="gray" size="lg">Plan Semilla</x-filament::badge>
                            <x-filament::badge color="{{ $this->isInDangerZone() ? 'danger' : ($this->percentage >= 80 ? 'warning' : 'success') }}" size="lg">
                                {{ $this->percentage }}% usado
                            </x-filament::badge>
                        </div>

                        <div>
                            <p class="biz-kicker">Productos publicados</p>
                            <p class="biz-stat-value">{{ $this->currentCount }} / {{ $this->limit }}</p>
                            <p class="biz-muted">{{ $this->getStatusMessage() }}</p>
                        </div>
                    </div>

                    <x-filament::button
                        tag="a"
                        color="{{ $this->isInDangerZone() ? 'warning' : 'gray' }}"
                        icon="heroicon-o-arrow-up-right"
                        href="{{ $this->upgradeUrl }}"
                    >
                        Ver opciones de PRO
                    </x-filament::button>
                </div>

                <div
                    class="biz-progress"
                    role="progressbar"
                    aria-label="Uso del limite de productos"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ min(100, $this->percentage) }}"
                >
                    <div
                        class="biz-progress-bar {{ $this->percentage >= 90 ? 'biz-progress-bar-danger' : ($this->percentage >= 80 ? 'biz-progress-bar-warning' : '') }}"
                        style="width: {{ max(6, min(100, $this->percentage)) }}%;"
                    ></div>
                </div>

                <div class="biz-actions">
                    <div class="biz-action">
                        <p class="biz-action-title">{{ max(0, $this->limit - $this->currentCount) }} espacios disponibles</p>
                        <p class="biz-action-copy">Usa el espacio restante para productos que el cliente pueda entender y comprar rapido.</p>
                    </div>

                    <div class="biz-action">
                        <p class="biz-action-title">Upgrade opcional</p>
                        <p class="biz-action-copy">PRO tiene sentido cuando necesitas mas productos, reservas/reportes, campanas o IA privada con activacion segura.</p>
                    </div>
                </div>

                @if ($this->isInDangerZone())
                    <div class="biz-card-warning" data-testid="dashboard-product-limit-warning-inline">
                        <div class="biz-split">
                            <div>
                                <p class="biz-action-title">Estas cerca del limite del catalogo</p>
                                <p class="biz-muted">Revisa si conviene ordenar productos existentes o pasar a PRO antes de agregar mas catalogo.</p>
                            </div>

                            <x-filament::badge color="warning">Revisar plan</x-filament::badge>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
