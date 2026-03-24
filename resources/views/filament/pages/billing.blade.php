<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card-accent">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div class="biz-badge biz-badge-success">Finanzas</div>

                    <div>
                        <p class="biz-kicker">Facturación de la plataforma</p>
                        <h1 class="biz-title">Pagos, precio PRO y soporte</h1>
                        <p class="biz-copy">
                            Monitorea el flujo manual de comprobantes y verifica de un vistazo cómo está funcionando el negocio Base vs. PRO.
                        </p>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <p class="biz-stat-label">Precio vigente</p>
                        <p class="biz-stat-value">{{ $proPlanPrice }}</p>
                        <p class="biz-stat-copy">Valor de referencia del plan PRO mensual para negocios.</p>

                        <div class="biz-button-row">
                            <a href="{{ route('filament.admin.resources.payments.index') }}" class="biz-button">
                                Revisar pagos
                            </a>
                            <a href="{{ route('filament.admin.pages.manage-general-settings') }}" class="biz-button-secondary">
                                Ajustar configuración
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-metrics">
            <div class="biz-stat">
                <p class="biz-stat-label">Pendientes</p>
                <p class="biz-stat-value">{{ number_format($pendingPayments) }}</p>
                <p class="biz-stat-copy">Comprobantes por revisar</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Aprobados</p>
                <p class="biz-stat-value">{{ number_format($approvedPayments) }}</p>
                <p class="biz-stat-copy">Pagos validados</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Rechazados</p>
                <p class="biz-stat-value">{{ number_format($rejectedPayments) }}</p>
                <p class="biz-stat-copy">Casos con observación</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Negocios PRO</p>
                <p class="biz-stat-value">{{ number_format($proBusinesses) }}</p>
                <p class="biz-stat-copy">Cuentas premium activas</p>
            </div>

            <div class="biz-stat">
                <p class="biz-stat-label">Negocios Base</p>
                <p class="biz-stat-value">{{ number_format($baseBusinesses) }}</p>
                <p class="biz-stat-copy">Cuentas en modo esencial</p>
            </div>
        </div>

        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Embudo comercial</p>
                        <h2 class="biz-heading">Señales para activar crecimiento</h2>
                        <p class="biz-muted">
                            Esta lectura resume qué tan cerca están los negocios de verse listos, cobrar y convertirse a PRO.
                        </p>
                    </div>

                    <div class="biz-metrics">
                        <div class="biz-stat">
                            <p class="biz-stat-label">Con catálogo</p>
                            <p class="biz-stat-value">{{ number_format($businessesWithCatalog) }}</p>
                            <p class="biz-stat-copy">Negocios que ya tienen productos visibles.</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Con pagos</p>
                            <p class="biz-stat-value">{{ number_format($businessesWithPayments) }}</p>
                            <p class="biz-stat-copy">Negocios que ya dejaron evidencia de intención comercial.</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Conversión PRO</p>
                            <p class="biz-stat-value">{{ number_format($proConversionRate, 1) }}%</p>
                            <p class="biz-stat-copy">Porcentaje del total que ya activó un plan premium.</p>
                        </div>
                    </div>
                </div>

                <div class="biz-stat">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-stat-label">Playbook rápido</p>
                            <p class="biz-stat-copy">
                                1. Empuja catálogo primero. 2. Sigue pagos reportados o cuentas con interés comercial.
                                3. Ofrece PRO a quienes ya muestran tracción o mejor presencia.
                            </p>
                        </div>

                        <div class="biz-progress">
                            <div class="biz-progress-bar" style="width: {{ max(6, $proConversionRate) }}%;"></div>
                        </div>

                        <p class="biz-stat-copy">
                            Cada punto de conversión importa más si el negocio ya cargó catálogo y mantiene seguimiento de cobro.
                        </p>

                        <div class="biz-button-row">
                            <a href="{{ route('filament.admin.resources.tenants.index') }}" class="biz-button-secondary">
                                Revisar negocios
                            </a>
                            <a href="{{ route('filament.admin.resources.payments.index') }}" class="biz-button-secondary">
                                Revisar pagos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Ciudades</p>
                        <h2 class="biz-heading">Tracción por ciudad</h2>
                        <p class="biz-muted">
                            Donde hay más base instalada y mejor oportunidad de conversión a PRO.
                        </p>
                    </div>

                    @if ($cityFunnel !== [])
                        <div class="biz-list">
                            @foreach ($cityFunnel as $city)
                                @php($conversion = (float) str_replace('%', '', (string) $city['conversion']))

                                <div class="biz-checklist-item">
                                    <div style="width: 100%;">
                                        <div class="biz-split">
                                            <p class="biz-checklist-title">{{ $city['name'] }}</p>
                                            <x-filament::badge color="{{ $conversion >= 40 ? 'success' : ($conversion >= 20 ? 'warning' : 'gray') }}">
                                                {{ $city['conversion'] }} PRO
                                            </x-filament::badge>
                                        </div>

                                        <p class="biz-checklist-copy">
                                            {{ number_format($city['total']) }} negocios · {{ number_format($city['pro']) }} PRO
                                        </p>

                                        <div class="biz-progress" style="margin-top: 0.75rem;">
                                            <div class="biz-progress-bar {{ $conversion < 20 ? 'biz-progress-bar-warning' : '' }}" style="width: {{ max(6, $conversion) }}%;"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'heading' => 'Aún no hay lectura por ciudad',
                            'description' => 'Cuando existan negocios suficientes, aquí verás dónde conviene enfocar onboarding y seguimiento comercial.',
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Categorías</p>
                        <h2 class="biz-heading">Tracción por categoría</h2>
                        <p class="biz-muted">
                            Lectura rápida para detectar verticales con mejor adopción y potencial premium.
                        </p>
                    </div>

                    @if ($categoryFunnel !== [])
                        <div class="biz-list">
                            @foreach ($categoryFunnel as $category)
                                @php($conversion = (float) str_replace('%', '', (string) $category['conversion']))

                                <div class="biz-checklist-item">
                                    <div style="width: 100%;">
                                        <div class="biz-split">
                                            <p class="biz-checklist-title">{{ $category['name'] }}</p>
                                            <x-filament::badge color="{{ $conversion >= 40 ? 'success' : ($conversion >= 20 ? 'warning' : 'gray') }}">
                                                {{ $category['conversion'] }} PRO
                                            </x-filament::badge>
                                        </div>

                                        <p class="biz-checklist-copy">
                                            {{ number_format($category['total']) }} negocios · {{ number_format($category['pro']) }} PRO
                                        </p>

                                        <div class="biz-progress" style="margin-top: 0.75rem;">
                                            <div class="biz-progress-bar {{ $conversion < 20 ? 'biz-progress-bar-warning' : '' }}" style="width: {{ max(6, $conversion) }}%;"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'heading' => 'Aún no hay lectura por categoría',
                            'description' => 'Cuando la base crezca, aquí verás qué verticales responden mejor al plan PRO y al trabajo comercial.',
                        ])
                    @endif
                </div>
            </div>
        </div>

        <div class="biz-grid-two">
            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Revisión operativa</p>
                        <h2 class="biz-heading">Últimos pagos reportados</h2>
                        <p class="biz-muted">
                            Historial corto para actuar rápido sobre solicitudes recientes.
                        </p>
                    </div>

                    @if ($recentPayments !== [])
                        <div class="biz-table">
                            <table class="biz-table-grid">
                                <thead>
                                    <tr class="biz-table-head-row">
                                        <th class="biz-table-head-cell">Negocio</th>
                                        <th class="biz-table-head-cell">Referencia</th>
                                        <th class="biz-table-head-cell">Monto</th>
                                        <th class="biz-table-head-cell">Estado</th>
                                        <th class="biz-table-head-cell">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentPayments as $payment)
                                        <tr class="biz-table-row">
                                            <td class="biz-table-cell biz-table-cell-strong">{{ $payment['business'] }}</td>
                                            <td class="biz-table-cell biz-table-cell-mono">{{ $payment['reference'] }}</td>
                                            <td class="biz-table-cell">{{ $payment['amount'] }}</td>
                                            <td class="biz-table-cell">
                                                <x-filament::badge color="{{ $payment['status'] === 'Aprobado' ? 'success' : ($payment['status'] === 'Pendiente' ? 'warning' : 'danger') }}">
                                                    {{ $payment['status'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="biz-table-cell biz-table-cell-muted">{{ $payment['reported_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        @include('filament.app.components.table-empty-state', [
                            'heading' => 'Todavía no hay pagos reportados',
                            'description' => 'Cuando un negocio suba un comprobante o el equipo registre una revisión, aparecerá aquí.',
                        ])
                    @endif
                </div>
            </div>

            <div class="biz-card">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">Canales</p>
                        <h2 class="biz-heading">Soporte y seguimiento</h2>
                        <p class="biz-muted">
                            Referencias rápidas para atender cobros, activaciones y consultas.
                        </p>
                    </div>

                    <div class="biz-list">
                        <div class="biz-stat">
                            <p class="biz-stat-label">WhatsApp soporte</p>
                            <p class="biz-stat-value" style="font-size: 1.35rem;">{{ $supportWhatsApp }}</p>
                            <p class="biz-stat-copy">Canal principal para acompañamiento manual.</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Correo soporte</p>
                            <p class="biz-stat-value" style="font-size: 1.35rem;">{{ $supportEmail }}</p>
                            <p class="biz-stat-copy">Correo de referencia para pagos y seguimiento.</p>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-label">Flujo recomendado</p>
                            <p class="biz-stat-copy">
                                1. Revisar pendientes. 2. Aprobar o rechazar con nota clara. 3. Ajustar precio o instrucciones desde configuración general.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
