<x-filament-panels::page>
    <div class="biz-stack" data-testid="billing-page">
        @if($isPending)
            <section class="biz-card-warning" data-testid="billing-pending-state">
                <div class="biz-stack">
                    <div class="biz-button-row">
                        <x-filament::badge color="warning" size="lg">
                            Pago en revision
                        </x-filament::badge>

                        <span class="biz-muted">
                            Referencia: <strong>{{ $pendingPayment->reference_number ?? 'N/A' }}</strong>
                        </span>
                    </div>

                    <div>
                        <h2 class="biz-title">Tu comprobante esta en revision</h2>
                        <p class="biz-muted">
                            Ya recibimos el pago reportado. Cuando el equipo valide la transferencia,
                            activaremos el plan y veras el cambio reflejado en este panel.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="biz-card">
                            <x-filament::badge color="success">Recibido</x-filament::badge>
                            <p class="biz-heading">Comprobante cargado</p>
                            <p class="biz-muted">El archivo quedo asociado a tu negocio.</p>
                        </div>

                        <div class="biz-card">
                            <x-filament::badge color="warning">En proceso</x-filament::badge>
                            <p class="biz-heading">Validacion manual</p>
                            <p class="biz-muted">Revisamos referencia, monto y banco antes de activar.</p>
                        </div>

                        <div class="biz-card">
                            <x-filament::badge color="gray">Siguiente</x-filament::badge>
                            <p class="biz-heading">Plan activo</p>
                            <p class="biz-muted">Te notificaremos cuando el plan quede habilitado.</p>
                        </div>
                    </div>
                </div>
            </section>
        @elseif($isPro)
            <section class="biz-card-accent" data-testid="billing-pro-state">
                <div class="biz-stack">
                    <div class="biz-button-row">
                        <x-filament::badge color="success" size="lg">
                            {{ $this->billingBadge() }}
                        </x-filament::badge>

                        @if($expiresAt)
                            <x-filament::badge :color="$daysRemaining <= 7 ? 'warning' : 'gray'" size="lg">
                                Vigente hasta {{ $expiresAt }}
                            </x-filament::badge>
                        @endif
                    </div>

                    <div>
                        <h2 class="biz-title">{{ $this->billingHeroTitle() }}</h2>
                        <p class="biz-muted">{{ $this->billingHeroCopy() }}</p>
                    </div>

                    @if($expiresAt)
                        <div class="biz-card">
                            <p class="biz-kicker">Vigencia</p>
                            <p class="biz-heading">
                                {{ $daysRemaining > 0 ? $daysRemaining.' dias restantes' : 'Vence hoy' }}
                            </p>
                            <p class="biz-muted">
                                @if($daysRemaining <= 7)
                                    Renueva antes del {{ $expiresAt }} para mantener las funciones premium sin interrupciones.
                                @else
                                    Tu plan esta activo y no requiere accion inmediata.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </section>

            @if($daysRemaining <= 7 && $daysRemaining > 0)
                <x-filament::section>
                    <x-slot name="heading">Renovacion cercana</x-slot>
                    <x-slot name="description">
                        Tu suscripcion vence pronto. Usa la accion de renovacion en el encabezado si ya tienes listo el pago.
                    </x-slot>
                </x-filament::section>
            @endif

            <section class="biz-card">
                <div class="biz-stack">
                    <div>
                        <h2 class="biz-heading">Historial de pagos</h2>
                        <p class="biz-muted">Pagos aprobados, pendientes y rechazados asociados a este negocio.</p>
                    </div>

                    @if($paymentsTableAvailable)
                        {{ $this->table }}
                    @else
                        <div class="biz-empty-state" data-testid="billing-history-unavailable">
                            <p class="biz-action-title">Historial no disponible en este entorno</p>
                            <p class="biz-muted">La tabla local de pagos no existe. El panel sigue mostrando el estado del plan sin ejecutar acciones de pago.</p>
                        </div>
                    @endif
                </div>
            </section>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.8fr)]" data-testid="billing-free-payment-form">
                <section class="biz-card">
                    <div class="biz-stack">
                        <div class="biz-button-row">
                            <x-filament::badge color="warning" size="lg">
                                {{ $this->billingBadge() }}
                            </x-filament::badge>
                            <x-filament::badge color="gray" size="lg">
                                {{ $formattedPrice }} / mes
                            </x-filament::badge>
                        </div>

                        <div>
                            <h2 class="biz-title">{{ $this->billingHeroTitle() }}</h2>
                            <p class="biz-muted">{{ $this->billingHeroCopy() }}</p>
                        </div>

                        {{ $this->form }}
                    </div>
                </section>

                <aside class="biz-stack">
                    <section class="biz-card-accent">
                        <div class="biz-stack">
                            <div>
                                <p class="biz-kicker">Resumen del plan</p>
                                <h3 class="biz-heading">{{ $this->billingPlanHeading() }}</h3>
                                <p class="biz-muted">Activa el plan cuando estes listo para vender con mas herramientas.</p>
                            </div>

                            <div class="biz-list">
                                <div class="biz-split-item">
                                    <span class="biz-muted">Monto</span>
                                    <strong>{{ $formattedPrice }}</strong>
                                </div>
                                <div class="biz-split-item">
                                    <span class="biz-muted">Revision</span>
                                    <strong>Manual</strong>
                                </div>
                                <div class="biz-split-item">
                                    <span class="biz-muted">Estado actual</span>
                                    <strong>Plan base</strong>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="biz-card">
                        <div class="biz-stack">
                            <h3 class="biz-heading">Antes de enviar</h3>
                            <ul class="biz-list">
                                <li class="biz-muted">Confirma que el monto coincide con el plan.</li>
                                <li class="biz-muted">Sube una captura clara del comprobante.</li>
                                <li class="biz-muted">Usa el numero de referencia exacto de la transferencia.</li>
                            </ul>
                        </div>
                    </section>
                </aside>
            </div>
        @endif
    </div>
</x-filament-panels::page>
