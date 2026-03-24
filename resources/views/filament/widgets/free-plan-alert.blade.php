<x-filament-widgets::widget>
    <div class="biz-card-warning">
        <div class="biz-grid-two">
            <div class="biz-stack">
                <div class="biz-badge biz-badge-warning">Plan Base</div>

                <div>
                    <h2 class="biz-heading">Tu negocio ya pide el siguiente paso</h2>
                    <p class="biz-copy">
                        Activa PRO para habilitar pedidos por WhatsApp, una presencia pública más completa y mayor capacidad de catálogo.
                    </p>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Uso actual</p>
                        <p class="biz-stat-value">{{ $productCount }} / {{ $limit }}</p>
                        <p class="biz-stat-copy">{{ $percentage }}% del límite del plan base.</p>
                    </div>

                    <div class="biz-button-row">
                        <a
                            href="https://wa.me/50499999999?text=Quiero%20activar%20mi%20Plan%20PRO%20en%20Conecta%20HN"
                            target="_blank"
                            class="biz-button"
                        >
                            Hablar con soporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
