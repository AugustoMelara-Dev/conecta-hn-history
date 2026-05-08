<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Acciones frecuentes
        </x-slot>

        <x-slot name="description">
            Atajos para las tareas que mas mueven la operacion diaria.
        </x-slot>

        <div class="biz-stack" data-testid="quick-actions-widget">
            <div class="biz-actions">
                <a href="{{ route('filament.app.resources.products.create', ['tenant' => $tenant?->slug]) }}" class="biz-action">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Catalogo</p>
                            <p class="biz-action-title">Agregar producto</p>
                        </div>
                        <x-filament::badge color="success">Crear</x-filament::badge>
                    </div>
                    <p class="biz-action-copy">Publica una nueva oferta sin salir del panel canonico.</p>
                </a>

                <a href="{{ route('filament.app.pages.settings', ['tenant' => $tenant?->slug]) }}" class="biz-action">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Estado</p>
                            <p class="biz-action-title">{{ $isOpen ? 'Negocio abierto' : 'Negocio cerrado' }}</p>
                        </div>
                        <x-filament::badge color="{{ $isOpen ? 'success' : 'danger' }}">
                            {{ $isOpen ? 'Disponible' : 'Revisar horario' }}
                        </x-filament::badge>
                    </div>
                    <p class="biz-action-copy">Ajusta horarios, telefono y datos visibles para clientes.</p>
                </a>

                <a href="{{ url('/' . $tenant?->slug) }}" target="_blank" rel="noopener noreferrer" class="biz-action">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Vista publica</p>
                            <p class="biz-action-title">Abrir micrositio</p>
                        </div>
                        <x-filament::badge color="gray">Nuevo tab</x-filament::badge>
                    </div>
                    <p class="biz-action-copy">Revisa como ve el cliente tu pagina y tu catalogo.</p>
                </a>

                <a href="{{ route('filament.app.pages.settings', ['tenant' => $tenant?->slug]) }}" class="biz-action">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Cuenta</p>
                            <p class="biz-action-title">Configurar negocio</p>
                        </div>
                        <x-filament::badge color="info">Ajustes</x-filament::badge>
                    </div>
                    <p class="biz-action-copy">Mantén datos, marca y preferencias alineadas.</p>
                </a>
            </div>

            <div class="biz-empty-state">
                <p class="biz-action-title">{{ $productsCount }} productos en tu catalogo</p>
                <p class="biz-muted">Usa esta lectura como una senal rapida de profundidad comercial, no como limite del plan.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
