<x-filament-panels::page>
    @php
        $tenantSlug = filament()->getTenant()?->slug ?? '';
    @endphp

    <div class="biz-shell">
        <div class="biz-card-warning">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-warning">{{ $plan['marketingLabel'] }}</div>

                    <div>
                        <h1 class="biz-title">Desbloquea mas herramientas para tu negocio</h1>
                        <p class="biz-copy">
                            {{ $plan['marketingCopy'] }} {{ $plan['brandingCopy'] }}
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <p class="biz-kicker">Precio</p>
                        <div>
                            <p class="biz-title">{{ $plan['priceLabel'] }}</p>
                            <p class="biz-muted">{{ $plan['billingCta'] }}</p>
                        </div>

                        <p class="biz-muted">
                            La activacion y el envio del comprobante se gestionan desde la seccion de Suscripcion.
                        </p>

                        <div class="biz-button-row">
                            <a href="{{ route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]) }}" class="biz-button">
                                {{ $plan['billingCta'] }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Incluye</p>
                    <h2 class="biz-heading">Lo que obtienes con PRO</h2>
                </div>

                <div class="biz-metrics">
                    @foreach ($features as $feature)
                        <div class="biz-stat">
                            <p class="biz-action-title">{{ $feature['title'] }}</p>
                            <p class="biz-action-copy">{{ $feature['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
