<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Plan base activo
        </x-slot>

        <x-slot name="description">
            Puedes seguir operando con el plan actual. PRO tiene sentido cuando necesitas mas catalogo, marca y seguimiento comercial.
        </x-slot>

        <div class="biz-stack" data-testid="free-plan-upsell-widget">
            <div class="biz-button-row">
                <x-filament::badge color="gray" size="lg">Plan Semilla</x-filament::badge>
                <x-filament::badge color="warning" size="lg">Upgrade opcional</x-filament::badge>
            </div>

            <div class="biz-actions">
                <div class="biz-action">
                    <p class="biz-action-title">WhatsApp visible</p>
                    <p class="biz-action-copy">Reduce preguntas repetidas y lleva al cliente al canal correcto.</p>
                </div>

                <div class="biz-action">
                    <p class="biz-action-title">Marca y catalogo mas completos</p>
                    <p class="biz-action-copy">Usa portada, colores y mas productos cuando el negocio lo necesite.</p>
                </div>

                <div class="biz-action">
                    <p class="biz-action-title">Seguimiento comercial</p>
                    <p class="biz-action-copy">Ordena la presencia publica y las senales que ayudan a decidir.</p>
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
