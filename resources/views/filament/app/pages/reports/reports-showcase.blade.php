<x-filament-panels::page>
    <main class="grid gap-6" aria-labelledby="report-showcase-heading" data-testid="reports-showcase">
        <header class="biz-card">
            <p class="biz-kicker">Vista del reporte</p>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 id="report-showcase-heading" class="biz-title">{{ $report?->getName() ?? 'Reporte no disponible' }}</h2>
                    <p class="biz-muted">{{ $report?->getDescription() ?? 'Selecciona un reporte registrado desde la biblioteca.' }}</p>
                </div>
                @if($report)
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="text-sm font-medium">Período
                            <select wire:model.live="range" class="mt-1 block rounded-lg border-gray-300 dark:bg-gray-900" aria-label="Rango del reporte">
                                <option value="today">Hoy</option><option value="7d">7 días</option><option value="30d">30 días</option><option value="qtd">Trimestre</option><option value="ytd">Año</option>
                            </select>
                        </label>
                        <x-filament::button wire:click="export('pdf')" color="gray" aria-label="Exportar reporte en PDF">PDF</x-filament::button>
                        <x-filament::button wire:click="export('csv')" aria-label="Exportar reporte en CSV">CSV</x-filament::button>
                    </div>
                @endif
            </div>
        </header>

        @if($report && $data)
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Resumen del reporte">
                @forelse($data['summary'] as $label => $value)
                    <div class="biz-card"><p class="biz-kicker">{{ str_replace('_', ' ', $label) }}</p><p class="text-2xl font-semibold">{{ is_numeric($value) ? number_format((float) $value, 2) : $value }}</p></div>
                @empty
                    <div class="biz-empty-state sm:col-span-2 xl:col-span-4" role="status">El reporte no tiene métricas de resumen para este período.</div>
                @endforelse
            </section>
            <section class="biz-card overflow-x-auto" aria-labelledby="report-results-heading">
                <h3 id="report-results-heading" class="biz-heading">Detalle operativo</h3>
                @if(count($data['rows']))
                    <table class="mt-4 w-full text-left text-sm"><thead><tr class="border-b"><th class="py-3">Indicador</th><th class="py-3 text-right">Valor</th></tr></thead><tbody>@foreach($data['rows'] as $row)<tr class="border-b border-gray-100 dark:border-white/10"><td class="py-3">{{ $row['label'] ?? '—' }}</td><td class="py-3 text-right font-medium">{{ $row['value'] ?? '—' }}</td></tr>@endforeach</tbody></table>
                @else
                    <p class="biz-muted mt-4" role="status">Sin movimientos en el período seleccionado.</p>
                @endif
            </section>
        @else
            <section class="biz-empty-state" role="status">No se pudo cargar un reporte registrado para el negocio activo.</section>
        @endif
    </main>
</x-filament-panels::page>
