<x-filament-panels::page>
    @php($metrics = $this->getMetrics())
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Plataforma</p>
            <h1 class="biz-title">Métricas del sistema</h1>
            <p class="biz-copy">Volumen operativo actual de las principales áreas de la plataforma.</p>
        </section>
        <section class="biz-metrics">
            @foreach ([
                'Negocios' => $metrics['tenants_count'],
                'Usuarios' => $metrics['users_count'],
                'Órdenes' => $metrics['orders_count'],
                'Contactos CRM' => $metrics['crm_contacts_count'],
                'Oportunidades CRM' => $metrics['crm_deals_count'],
                'Ejecuciones IA hoy' => $metrics['ai_runs_today'],
            ] as $label => $value)
                <article class="biz-stat"><p class="biz-stat-label">{{ $label }}</p><p class="biz-stat-value">{{ number_format($value) }}</p></article>
            @endforeach
        </section>
    </main>
</x-filament-panels::page>
