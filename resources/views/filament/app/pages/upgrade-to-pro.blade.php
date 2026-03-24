<x-filament-panels::page>
    @php
        $tenantSlug = filament()->getTenant()?->slug ?? '';
    @endphp

    <div class="biz-shell">
        <div class="biz-card-warning">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-warning">Mejora a PRO</div>

                    <div>
                        <h1 class="biz-title">Desbloquea más herramientas para tu negocio</h1>
                        <p class="biz-copy">
                            Si ya necesitas más alcance, mejor presencia pública y menos fricción operativa, este es el siguiente paso.
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <p class="biz-kicker">Precio</p>
                        <div>
                            <p class="biz-title">{{ $price }}</p>
                            <p class="biz-muted">por {{ $period }}</p>
                        </div>

                        <p class="biz-muted">
                            La activación y el envío del comprobante se gestionan desde la sección de Suscripción.
                        </p>

                        <div class="biz-button-row">
                            <a href="{{ route('filament.app.pages.manage-billing', ['tenant' => $tenantSlug]) }}" class="biz-button">
                                Ir a suscripción
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
