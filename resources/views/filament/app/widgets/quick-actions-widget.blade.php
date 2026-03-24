<x-filament-widgets::widget>
    <x-filament::section>
        <div class="biz-stack">
            <div>
                <p class="biz-kicker">Acciones rápidas</p>
                <h3 class="biz-heading">Atajos del negocio</h3>
                <p class="biz-muted">Entra a lo esencial sin pasar por pantallas intermedias.</p>
            </div>

            <div class="biz-grid-auto">
                <a href="{{ $createProductUrl }}" class="biz-action">
                    <div class="biz-stack" style="gap: 0.45rem;">
                        <p class="biz-action-title">Crear producto</p>
                        <p class="biz-action-copy">Agrega un nuevo ítem al catálogo.</p>
                    </div>
                </a>

                <a href="{{ $profileUrl }}" class="biz-action">
                    <div class="biz-stack" style="gap: 0.45rem;">
                        <div class="biz-split">
                            <p class="biz-action-title">Estado del negocio</p>
                            <span class="biz-badge {{ $isOpen ? 'biz-badge-success' : 'biz-badge-warning' }}">
                                {{ $isOpen ? 'Abierto' : 'Cerrado' }}
                            </span>
                        </div>

                        <p class="biz-action-copy">Revisa el perfil, horario y datos principales del negocio.</p>
                    </div>
                </a>

                <a href="{{ $publicUrl }}" target="_blank" class="biz-action">
                    <div class="biz-stack" style="gap: 0.45rem;">
                        <p class="biz-action-title">Ver página pública</p>
                        <p class="biz-action-copy">Abre tu micrositio como lo ve el cliente final.</p>
                    </div>
                </a>

                <a href="{{ $profileUrl }}" class="biz-action">
                    <div class="biz-stack" style="gap: 0.45rem;">
                        <p class="biz-action-title">Configuración</p>
                        <p class="biz-action-copy">Edita nombre, contacto, mensaje y detalles del negocio.</p>
                    </div>
                </a>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Catálogo actual</p>
                <p class="biz-stat-value">{{ $productsCount }}</p>
                <p class="biz-stat-copy">productos cargados en tu panel.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
