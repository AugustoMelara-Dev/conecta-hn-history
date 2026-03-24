<x-filament-widgets::widget>
    @if ($this->isPro)
        <div class="biz-card-accent">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-success">Plan PRO</div>

                    <div>
                        <p class="biz-kicker">Capacidad</p>
                        <h3 class="biz-heading">Catálogo sin límite operativo</h3>
                        <p class="biz-copy">
                            Tu negocio ya puede seguir creciendo sin toparse con el límite del plan Base.
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Estado del plan</p>
                            <p class="biz-stat-value">PRO</p>
                            <p class="biz-stat-copy">
                                @if ($this->planExpiresAt)
                                    Vigente hasta {{ $this->planExpiresAt }}
                                @else
                                    Funciones premium habilitadas.
                                @endif
                            </p>
                        </div>

                        <div class="biz-button-row">
                            <a href="{{ $this->billingUrl }}" class="biz-button-secondary">Gestionar suscripción</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="biz-card-warning">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-warning">Plan Base</div>

                    <div>
                        <p class="biz-kicker">Capacidad actual</p>
                        <h3 class="biz-heading">Uso del catálogo</h3>
                        <p class="biz-copy">{{ $this->getStatusMessage() }}</p>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Productos registrados</p>
                        <p class="biz-stat-value">{{ $this->currentCount }} / {{ $this->limit }}</p>
                        <p class="biz-stat-copy">{{ $this->percentage }}% del espacio usado.</p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Disponible</p>
                            <p class="biz-stat-value">{{ max(0, $this->limit - $this->currentCount) }}</p>
                            <p class="biz-stat-copy">Espacios restantes antes de necesitar PRO.</p>
                        </div>

                        <div class="biz-button-row">
                            <a href="{{ $this->upgradeUrl }}" class="biz-button">Mejorar a PRO</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-widgets::widget>
