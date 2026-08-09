<x-filament-panels::page>
    @php($metrics = $this->getAggregateData())
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Visión global</p>
            <h1 class="biz-title">Indicadores entre negocios</h1>
            <p class="biz-copy">Totales agregados de plataforma. Esta pantalla no expone registros de un tenant individual.</p>
            {{ $this->form }}
        </section>

        <section class="biz-metrics" aria-label="Métricas globales">
            @foreach ([
                'Negocios' => $metrics['total_tenants'],
                'Usuarios' => $metrics['total_users'],
                'Órdenes' => $metrics['total_orders'],
                'Ingresos' => 'L '.number_format((float) $metrics['total_revenue'], 2),
                'Contactos CRM' => $metrics['total_crm_contacts'],
                'Oportunidades' => $metrics['total_crm_deals'],
                'Ejecuciones IA' => $metrics['total_ai_calls'],
            ] as $label => $value)
                <article class="biz-stat">
                    <p class="biz-stat-label">{{ $label }}</p>
                    <p class="biz-stat-value">{{ is_numeric($value) ? number_format((float) $value) : $value }}</p>
                </article>
            @endforeach
        </section>
    </main>
</x-filament-panels::page>
