<x-filament-panels::page>
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Portafolio</p>
            <h1 class="biz-title">Negocios con mayor actividad</h1>
            <p class="biz-copy">Ranking por ingresos pagados para priorizar acompañamiento y soporte.</p>
        </section>
        <section class="biz-card biz-table-wrap">
            <table class="biz-table">
                <thead><tr><th>Negocio</th><th>Órdenes</th><th>Ingresos</th></tr></thead>
                <tbody>
                    @forelse ($this->getTopTenants() as $tenant)
                        <tr><td>{{ $tenant['name'] }}</td><td>{{ number_format($tenant['orders_count']) }}</td><td>L {{ number_format($tenant['revenue'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="3">Todavía no hay ventas pagadas para comparar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </main>
</x-filament-panels::page>
