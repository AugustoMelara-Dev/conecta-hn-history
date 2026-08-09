<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]" data-testid="reports-overview">
        <main class="space-y-8" aria-labelledby="reports-overview-heading">
            <header class="biz-card border-l-4 border-l-primary-600">
                <p class="biz-kicker">Centro de reportes</p>
                <h2 id="reports-overview-heading" class="biz-title">Una lectura clara de tu operación</h2>
                <p class="biz-muted max-w-3xl">Explora indicadores registrados y abre cada análisis con un rango controlado.</p>
            </header>

            @forelse($reportsByCategory as $category => $reports)
                <section aria-labelledby="report-category-{{ \Illuminate\Support\Str::slug($category) }}">
                    <div class="mb-3 flex items-end justify-between gap-4">
                        <h3 id="report-category-{{ \Illuminate\Support\Str::slug($category) }}" class="biz-heading">{{ $category }}</h3>
                        <span class="biz-muted">{{ count($reports) }} {{ count($reports) === 1 ? 'reporte' : 'reportes' }}</span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                        @foreach($reports as $report)
                            <a class="biz-card group focus:outline-none focus:ring-2 focus:ring-primary-600" href="{{ \App\Filament\App\Pages\Reports\ReportsShowcase::getUrl(['reportKey' => $report->getKey()->value], tenant: \Filament\Facades\Filament::getTenant()) }}" aria-label="Abrir reporte {{ $report->getName() }}">
                                <p class="biz-kicker">{{ $report->getKey()->category() }}</p>
                                <h4 class="biz-heading group-hover:text-primary-600">{{ $report->getName() }}</h4>
                                <p class="biz-muted">{{ $report->getDescription() }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @empty
                <section class="biz-empty-state" role="status" aria-labelledby="reports-empty-heading">
                    <h3 id="reports-empty-heading" class="biz-heading">Aún no hay reportes disponibles</h3>
                    <p class="biz-muted">Los reportes habilitados para tu operación aparecerán aquí.</p>
                </section>
            @endforelse
        </main>

        <aside class="biz-card h-fit xl:sticky xl:top-6" aria-labelledby="reports-guide-heading">
            <p class="biz-kicker">Guía de lectura</p>
            <h3 id="reports-guide-heading" class="biz-heading">De señal a decisión</h3>
            <ol class="mt-4 space-y-4 text-sm">
                <li><strong>01.</strong> Elige una categoría.</li>
                <li><strong>02.</strong> Ajusta el período.</li>
                <li><strong>03.</strong> Exporta sólo lo necesario.</li>
            </ol>
        </aside>
    </div>
</x-filament-panels::page>
