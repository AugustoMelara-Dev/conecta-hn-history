<x-filament-panels::page>
    @if ($isPending)
        <div class="biz-card-warning">
            <div class="biz-stack">
                <div class="biz-badge biz-badge-warning">Pago en revisión</div>

                <div>
                    <h1 class="biz-title">Tu pago está en revisión</h1>
                    <p class="biz-copy">
                        Ya recibimos tu comprobante. Cuando el equipo valide la transferencia, tu plan PRO quedará activo.
                    </p>
                </div>

                <div class="biz-metrics">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Referencia</p>
                        <p class="biz-stat-value" style="font-size: 1.4rem;">
                            {{ $pendingPayment->bank_reference ?? $pendingPayment->reference_number ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="biz-stat">
                        <p class="biz-stat-label">Estado</p>
                        <p class="biz-stat-value" style="font-size: 1.4rem;">Pendiente</p>
                    </div>
                    <div class="biz-stat">
                        <p class="biz-stat-label">Siguiente paso</p>
                        <p class="biz-stat-value" style="font-size: 1.4rem;">Revisión manual</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($isPro)
        <div class="biz-shell">
            <div class="biz-card-accent">
                <div class="biz-grid-two">
                    <div class="biz-stack">
                        <div class="biz-badge biz-badge-success">Plan PRO activo</div>
                        <div>
                            <h1 class="biz-title">Suscripción activa</h1>
                            <p class="biz-copy">
                                Tu negocio ya tiene habilitadas las funciones PRO. Desde aquí puedes revisar pagos, vigencia y seguimiento de renovación.
                            </p>
                        </div>
                    </div>

                    <div class="biz-metrics">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Plan</p>
                            <p class="biz-stat-value">PRO</p>
                            <p class="biz-stat-copy">Funciones premium activas</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Vigencia</p>
                            <p class="biz-stat-value" style="font-size: 1.45rem;">{{ $expiresAt ?? 'Sin fecha' }}</p>
                            <p class="biz-stat-copy">{{ $daysRemaining > 0 ? $daysRemaining . ' días restantes' : 'Revisar renovación' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Historial</p>
                        <h2 class="biz-heading">Pagos registrados</h2>
                    </div>

                    {{ $this->table }}
                </div>
            </div>
        </div>
    @else
        <div class="biz-grid-two">
            <div class="biz-card" wire:loading.class="opacity-80" wire:target="submitPayment">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Activación</p>
                        <h1 class="biz-title">Activa tu plan PRO</h1>
                        <p class="biz-copy">
                            Sigue el flujo para transferir, subir el comprobante y dejar tu negocio listo para revisión.
                        </p>
                    </div>

                    {{ $this->form }}

                    <div class="biz-empty-state" style="padding: 1rem; text-align: left;">
                        <p class="biz-action-copy">
                            Envía el comprobante desde el encabezado con <strong>Enviar pago a revisión</strong> para seguir el mismo flujo nativo del panel.
                        </p>
                        <p class="biz-note" wire:loading wire:target="submitPayment">Enviando comprobante...</p>
                    </div>
                </div>
            </div>

            <div class="biz-card-accent">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Plan</p>
                        <h2 class="biz-heading">PRO para negocio</h2>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Precio mensual</p>
                        <p class="biz-stat-value">{{ $formattedPrice }}</p>
                        <p class="biz-stat-copy">Pago por transferencia bancaria</p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-stat">
                            <p class="biz-action-title">Catálogo ampliado</p>
                            <p class="biz-action-copy">Aprovecha mejor tu vitrina y organiza el contenido con más flexibilidad.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Herramientas de marketing</p>
                            <p class="biz-action-copy">Genera QR, personaliza WhatsApp y prepara campañas cuando tu plan lo permita.</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Audiencia y seguimiento</p>
                            <p class="biz-action-copy">Consulta mejor tu audiencia y centraliza la operación del negocio.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
