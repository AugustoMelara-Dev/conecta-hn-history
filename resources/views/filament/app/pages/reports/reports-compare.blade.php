<x-filament-panels::page>
    <main class="grid gap-6" aria-labelledby="reports-compare-heading" data-testid="reports-compare">
        <header class="biz-card">
            <p class="biz-kicker">Comparar reportes</p>
            <h2 id="reports-compare-heading" class="biz-title">Dos lecturas, un contexto</h2>
            <p class="biz-muted">Contrasta dos reportes registrados dentro del mismo negocio y período.</p>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <label class="text-sm font-medium">Primer reporte<select wire:model.live="primaryKey" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Primer reporte"><option value="">Selecciona uno</option>@foreach($reports as $report)<option value="{{ $report->getKey()->value }}">{{ $report->getName() }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Segundo reporte<select wire:model.live="secondaryKey" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Segundo reporte"><option value="">Selecciona otro</option>@foreach($reports as $report)<option value="{{ $report->getKey()->value }}">{{ $report->getName() }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Período<select wire:model.live="range" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Período de comparación"><option value="7d">7 días</option><option value="30d">30 días</option><option value="qtd">Trimestre</option><option value="ytd">Año</option></select></label>
            </div>
        </header>

        @if($comparison)
            <section class="grid gap-6 lg:grid-cols-2" aria-label="Resultados comparados">
                @foreach(['primary', 'secondary'] as $side)
                    <article class="biz-card">
                        <p class="biz-kicker">{{ $side === 'primary' ? 'Lectura A' : 'Lectura B' }}</p>
                        <h3 class="biz-heading">{{ $comparison[$side]->getName() }}</h3>
                        <p class="biz-muted">{{ $comparison[$side]->getDescription() }}</p>
                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            @forelse($comparison[$side.'_data']['summary'] as $label => $value)
                                <div class="biz-split-item"><dt class="biz-muted">{{ str_replace('_', ' ', $label) }}</dt><dd class="font-semibold">{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</dd></div>
                            @empty
                                <div class="biz-empty-state sm:col-span-2" role="status">Sin métricas resumidas.</div>
                            @endforelse
                        </dl>
                    </article>
                @endforeach
            </section>
        @else
            <section class="biz-empty-state" role="status" aria-label="Estado de comparación">Selecciona dos reportes distintos para construir la comparación.</section>
        @endif
    </main>
</x-filament-panels::page>
