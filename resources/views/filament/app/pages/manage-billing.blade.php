<x-filament-panels::page>
    @if ($isPending)
        <div class="biz-card-warning">
            <div class="biz-stack">
                <div class="biz-badge biz-badge-warning">{{ $this->billingBadge() }}</div>

                <div>
                    <h1 class="biz-title">{{ $this->billingHeroTitle() }}</h1>
                    <p class="biz-copy">
                        {{ $this->billingHeroCopy() }}
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
                        <p class="biz-stat-value" style="font-size: 1.4rem;">Revision manual</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($isPro)
        <div class="biz-shell">
            <div class="biz-card-accent">
                <div class="biz-grid-two">
                    <div class="biz-stack">
                        <div class="biz-badge biz-badge-success">{{ $this->billingBadge() }}</div>
                        <div>
                            <h1 class="biz-title">{{ $this->billingHeroTitle() }}</h1>
                            <p class="biz-copy">
                                {{ $this->billingHeroCopy() }}
                            </p>
                        </div>
                    </div>

                    <div class="biz-metrics">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Plan</p>
                            <p class="biz-stat-value">{{ $planPresentation['label'] ?? 'PRO' }}</p>
                            <p class="biz-stat-copy">{{ $planPresentation['marketingCopy'] ?? 'Funciones premium activas' }}</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Vigencia</p>
                            <p class="biz-stat-value" style="font-size: 1.45rem;">{{ $expiresAt ?? 'Sin fecha' }}</p>
                            <p class="biz-stat-copy">{{ $daysRemaining > 0 ? $daysRemaining . ' dias restantes' : 'Revisar renovacion' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Historial</p>
                        <h2 class="biz-heading">{{ $this->billingPlanHeading() }}</h2>
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
                        <p class="biz-kicker">Activacion</p>
                        <h1 class="biz-title">{{ $this->billingHeroTitle() }}</h1>
                        <p class="biz-copy">
                            {{ $this->billingHeroCopy() }}
                        </p>
                    </div>

                    {{ $this->form }}

                    <div class="biz-empty-state" style="padding: 1rem; text-align: left;">
                        <p class="biz-action-copy">
                            Envia el comprobante desde el encabezado con <strong>Enviar pago a revisión</strong> para seguir el mismo flujo nativo del panel.
                        </p>
                        <p class="biz-note" wire:loading wire:target="submitPayment">Enviando comprobante...</p>
                    </div>
                </div>
            </div>

            <div class="biz-card-accent">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Plan</p>
                        <h2 class="biz-heading">{{ $this->billingPlanHeading() }}</h2>
                    </div>

                    <div class="biz-stat">
                        <p class="biz-stat-label">Precio mensual</p>
                        <p class="biz-stat-value">{{ $planPresentation['priceLabel'] ?? $formattedPrice }}</p>
                        <p class="biz-stat-copy">Pago por transferencia bancaria</p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-stat">
                            <p class="biz-action-title">Catalogo ampliado</p>
                            <p class="biz-action-copy">{{ $planPresentation['catalogCopy'] ?? 'Aprovecha mejor tu vitrina y organiza el contenido con mas flexibilidad.' }}</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Herramientas de marketing</p>
                            <p class="biz-action-copy">{{ $planPresentation['marketingCopy'] ?? 'Genera QR, personaliza WhatsApp y prepara campanas cuando tu plan lo permita.' }}</p>
                        </div>
                        <div class="biz-stat">
                            <p class="biz-action-title">Audiencia y seguimiento</p>
                            <p class="biz-action-copy">{{ $planPresentation['followersCopy'] ?? 'Consulta mejor tu audiencia y centraliza la operacion del negocio.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
