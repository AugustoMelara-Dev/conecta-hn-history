<x-filament-widgets::widget>
    @include('filament.components.panel-critical-styles')

    <div class="biz-card-warning">
        <div class="biz-grid-two">
            <div class="biz-stack">
                <div class="biz-badge biz-badge-warning">Plan Base</div>

                <div>
                    <p class="biz-kicker">Crecimiento</p>
                    <h3 class="biz-heading">Todavia puedes desbloquear mas herramientas</h3>
                    <p class="biz-copy">
                        Activa PRO para habilitar marketing, mayor capacidad de catalogo y una gestion mas completa del negocio.
                    </p>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div class="biz-list">
                        <div class="biz-stat">
                            <p class="biz-action-title">WhatsApp configurable</p>
                            <p class="biz-action-copy">Respuestas iniciales mas claras para tus clientes.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Campanas y QR</p>
                            <p class="biz-action-copy">Comparte tu negocio con materiales listos para usar.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Productos ilimitados</p>
                            <p class="biz-action-copy">Evita topar el catalogo cuando empieces a crecer.</p>
                        </div>
                    </div>

                    <div class="biz-button-row">
                        <a href="{{ $this->getUpgradeUrl() }}" class="biz-button">Ir a suscripcion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
