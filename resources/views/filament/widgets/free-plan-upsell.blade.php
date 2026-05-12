<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Plan base activo
        </x-slot>

        <x-slot name="description">
            Free sirve para validar: micrositio, directorio, contacto y hasta 10 productos. PRO tiene sentido cuando necesitas mas operacion.
        </x-slot>

        <div class="biz-stack" data-testid="free-plan-upsell-widget">
            <div class="biz-button-row">
                <x-filament::badge color="gray" size="lg">Plan Semilla</x-filament::badge>
                <x-filament::badge color="warning" size="lg">Upgrade opcional</x-filament::badge>
            </div>

            <div class="biz-actions">
                <div class="biz-action">
                    <p class="biz-action-title">Free para validar</p>
                    <p class="biz-action-copy">Micrositio, directorio, WhatsApp/llamada/mapa y limite inicial de 10 productos.</p>
                </div>

                <div class="biz-action">
                    <p class="biz-action-title">PRO para crecer</p>
                    <p class="biz-action-copy">Mas catalogo, reservas avanzadas, reportes, campanas y mejores senales comerciales.</p>
                </div>

                <div class="biz-action">
                    <p class="biz-action-title">IA privada activable</p>
                    <p class="biz-action-copy">El agente IA requiere PRO, allowlist por tenant y permiso ai.use antes de consultar datos privados.</p>
                </div>
            </div>

            <div class="biz-button-row">
                <a href="{{ $this->getUpgradeUrl() }}" class="biz-button">
                    Ver opciones de PRO
                </a>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
