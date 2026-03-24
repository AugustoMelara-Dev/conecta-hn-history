<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card-accent">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-success">Configuración</div>

                    <div>
                        <p class="biz-kicker">Superadmin</p>
                        <h1 class="biz-title">Ajustes globales de la plataforma</h1>
                        <p class="biz-copy">
                            Todo lo que cambies aquí afecta la experiencia de negocios, cobros, soporte y disponibilidad general del sistema.
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <p class="biz-stat-label">Qué controlas aquí</p>
                        <div class="biz-list">
                            <div class="biz-stat">
                                <p class="biz-action-title">Precios y límites</p>
                                <p class="biz-action-copy">Plan PRO, días de gracia y capacidad por plan.</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-action-title">Pagos y soporte</p>
                                <p class="biz-action-copy">Banco, instrucciones de transferencia y canales de ayuda.</p>
                            </div>

                            <div class="biz-stat">
                                <p class="biz-action-title">Disponibilidad del sistema</p>
                                <p class="biz-action-copy">Modo mantenimiento y control de nuevos registros.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Formulario</p>
                    <h2 class="biz-heading">Edita y guarda con seguridad</h2>
                    <p class="biz-muted">
                        Revisa cada campo antes de guardar; estos cambios impactan a toda la plataforma.
                    </p>
                </div>

                <form
                    wire:submit="save"
                    wire:loading.class="opacity-80"
                    wire:target="save"
                    class="biz-stack"
                >
                    {{ $this->form }}

                    <div class="biz-empty-state" style="padding: 1rem; text-align: left;">
                        <p class="biz-action-copy">
                            Guarda los cambios con el botón <strong>Guardar configuración</strong> del encabezado para seguir el mismo flujo nativo de Filament en todo el superpanel.
                        </p>
                        <p class="biz-note" wire:loading wire:target="save">Guardando ajustes globales...</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
