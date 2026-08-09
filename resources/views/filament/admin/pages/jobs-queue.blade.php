<x-filament-panels::page>
    @php($stats = $this->getQueueStats())
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Procesamiento asíncrono</p>
            <h1 class="biz-title">Colas y trabajos</h1>
            <p class="biz-copy">Lectura rápida del backlog local y de los trabajos que necesitan intervención.</p>
        </section>
        <section class="biz-metrics">
            <article class="biz-stat"><p class="biz-stat-label">Pendientes</p><p class="biz-stat-value">{{ number_format($stats['jobs_count']) }}</p></article>
            <article class="biz-stat"><p class="biz-stat-label">Fallidos</p><p class="biz-stat-value">{{ number_format($stats['failed_count']) }}</p></article>
        </section>
        @if (isset($stats['error']))
            <div class="biz-card-warning" role="alert">No se pudo consultar el backend de colas: {{ $stats['error'] }}</div>
        @endif
    </main>
</x-filament-panels::page>
