<x-filament-panels::page>
    <main class="grid gap-6" aria-labelledby="reports-library-heading" data-testid="reports-library">
        <header class="biz-card">
            <p class="biz-kicker">Biblioteca de reportes</p>
            <h2 id="reports-library-heading" class="biz-title">Catálogo analítico</h2>
            <p class="biz-muted">Una referencia compacta de todos los reportes registrados para este panel.</p>
        </header>

        <section class="grid gap-px overflow-hidden rounded-xl border border-gray-200 bg-gray-200 sm:grid-cols-2 xl:grid-cols-3 dark:border-white/10 dark:bg-white/10" aria-label="Reportes registrados">
            @forelse($reports as $report)
                <article class="bg-white p-5 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="biz-kicker">{{ $report->getKey()->category() }}</p>
                            <h3 class="biz-heading">{{ $report->getName() }}</h3>
                        </div>
                        <x-filament::badge color="gray">{{ $report->getChartType() }}</x-filament::badge>
                    </div>
                    <p class="biz-muted mt-3">{{ $report->getDescription() }}</p>
                    <a class="mt-5 inline-flex text-sm font-semibold text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-600" href="{{ \App\Filament\App\Pages\Reports\ReportsShowcase::getUrl(['key' => $report->getKey()->value], tenant: \Filament\Facades\Filament::getTenant()) }}" aria-label="Consultar {{ $report->getName() }}">Consultar reporte</a>
                </article>
            @empty
                <div class="biz-empty-state sm:col-span-2 xl:col-span-3" role="status">No hay reportes registrados.</div>
            @endforelse
        </section>
    </main>
</x-filament-panels::page>
