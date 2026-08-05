<x-filament-panels::page>
    <div class="biz-stack" data-testid="billing-page">
        <div class="grid gap-6 lg:grid-cols-2" data-testid="billing-plan-and-usage">
            <section class="biz-card" aria-labelledby="billing-current-plan-heading" data-testid="billing-entitlements">
                <div class="biz-stack">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Suscripcion de Dovra AI</p>
                            <h2 id="billing-current-plan-heading" class="biz-title">
                                Plan {{ $planEntitlements['name'] ?? 'Semilla' }}
                            </h2>
                            <p class="biz-muted">{{ $planEntitlements['description'] ?? '' }}</p>
                        </div>
                        <x-filament::badge :color="$isPro ? 'success' : ($isPending ? 'warning' : 'gray')" size="lg">
                            {{ $this->billingBadge() }}
                        </x-filament::badge>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2" aria-label="Limites incluidos en el plan">
                        <div class="biz-split-item">
                            <dt class="biz-muted">Productos</dt>
                            <dd><strong>{{ number_format((int) data_get($planEntitlements, 'limits.products', 0)) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Servicios</dt>
                            <dd><strong>{{ number_format((int) data_get($planEntitlements, 'limits.services', 0)) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Usuarios</dt>
                            <dd><strong>{{ number_format((int) data_get($planEntitlements, 'limits.users', 0)) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Ubicaciones</dt>
                            <dd><strong>{{ number_format((int) data_get($planEntitlements, 'limits.locations', 0)) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Automatizaciones</dt>
                            <dd><strong>{{ number_format((int) data_get($planEntitlements, 'limits.automations', 0)) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Almacenamiento</dt>
                            <dd><strong>{{ number_format(((int) data_get($planEntitlements, 'limits.storage_mb', 0)) / 1024, 1) }} GB</strong></dd>
                        </div>
                    </dl>

                    <div class="biz-empty-state">
                        <p class="biz-action-title">0% de comision sobre tus ventas</p>
                        <p class="biz-muted">
                            La suscripcion paga el uso de Dovra AI. Los pagos de tus clientes pertenecen a tu negocio:
                            no los custodiamos ni descontamos una comision.
                        </p>
                    </div>
                </div>
            </section>

            <section class="biz-card" aria-labelledby="billing-ai-usage-heading" data-testid="billing-ai-usage">
                <div class="biz-stack">
                    <div class="biz-split">
                        <div>
                            <p class="biz-kicker">Creditos internos de IA</p>
                            <h2 id="billing-ai-usage-heading" class="biz-title">Consumo del mes</h2>
                        </div>
                        <x-filament::badge
                            :color="match($aiUsage['status'] ?? 'healthy') {
                                'exhausted' => 'danger',
                                'critical', 'warning' => 'warning',
                                default => 'success',
                            }"
                            size="lg"
                        >
                            @switch($aiUsage['status'] ?? 'healthy')
                                @case('exhausted') Limite mensual alcanzado @break
                                @case('critical') 90% consumido @break
                                @case('warning') 70% consumido @break
                                @default Consumo saludable
                            @endswitch
                        </x-filament::badge>
                    </div>

                    <div>
                        <div class="biz-split-item">
                            <span class="biz-muted">Usados este mes</span>
                            <strong>
                                {{ number_format((float) ($aiUsage['spent'] ?? 0), 2) }}
                                de {{ number_format((float) ($aiUsage['allowance'] ?? 0), 2) }} creditos
                            </strong>
                        </div>
                        <div
                            class="mt-2 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                            role="progressbar"
                            aria-label="Creditos de IA consumidos este mes"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ (int) ($aiUsage['percentage'] ?? 0) }}"
                            aria-valuetext="{{ (int) ($aiUsage['percentage'] ?? 0) }} por ciento consumido"
                        >
                            <div
                                class="h-full rounded-full {{ ($aiUsage['threshold'] ?? 0) >= 90 ? 'bg-danger-600' : (($aiUsage['threshold'] ?? 0) >= 70 ? 'bg-warning-500' : 'bg-primary-600') }}"
                                style="width: {{ min(100, max(0, (int) ($aiUsage['percentage'] ?? 0))) }}%"
                            ></div>
                        </div>
                    </div>

                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="biz-split-item">
                            <dt class="biz-muted">Disponibles ahora</dt>
                            <dd><strong>{{ number_format((float) ($aiUsage['available'] ?? 0), 2) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Reservados en solicitudes</dt>
                            <dd><strong>{{ number_format((float) ($aiUsage['reserved'] ?? 0), 2) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Tope diario</dt>
                            <dd><strong>{{ number_format((float) ($aiUsage['daily_limit'] ?? 0), 2) }}</strong></dd>
                        </div>
                        <div class="biz-split-item">
                            <dt class="biz-muted">Reinicio mensual</dt>
                            <dd><strong>{{ $aiUsage['reset_label'] ?? 'Al iniciar el mes UTC' }}</strong></dd>
                        </div>
                    </dl>

                    @if(($aiUsage['threshold'] ?? 0) >= 70)
                        <div
                            class="biz-card-warning"
                            role="status"
                            data-testid="billing-ai-threshold-alert"
                        >
                            <p class="biz-action-title">
                                {{ ($aiUsage['threshold'] ?? 0) >= 100 ? 'La IA queda pausada de forma segura' : 'Estas cerca del limite incluido' }}
                            </p>
                            <p class="biz-muted">
                                No generaremos cargos sorpresa. Las solicitudes que excedan el limite se bloquean antes de llamar al proveedor;
                                los fallos liberan automaticamente cualquier reserva.
                            </p>
                        </div>
                    @else
                        <p class="biz-note">
                            Ves creditos comerciales, no unidades tecnicas del proveedor. Una solicitud fallida no descuenta saldo.
                        </p>
                    @endif
                </div>
            </section>
        </div>
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

            @if($daysRemaining <= 7)
                <x-filament::section data-testid="billing-renewal-form">
                    <x-slot name="heading">Renovacion cercana</x-slot>
                    <x-slot name="description">
                        Tu suscripcion vence pronto. Reporta aqui la transferencia; la nueva vigencia se suma a la fecha actual al aprobarse.
                    </x-slot>

                    @if(strtolower((string) config('services.payment.provider', 'generic')) !== 'stripe')
                        {{ $this->form }}
                    @else
                        <p class="biz-muted">Usa la accion Renovar suscripcion para completar el pago seguro con tarjeta.</p>
                    @endif
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
