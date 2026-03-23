<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Eventos totales</p>
                    <p class="mt-2 text-3xl font-bold text-zinc-950 dark:text-white">{{ $this->summary['total_events'] ?? 0 }}</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Cumplen</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-500">{{ $this->summary['compliant_count'] ?? 0 }}</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Incumplimientos</p>
                    <p class="mt-2 text-3xl font-bold {{ ($this->summary['non_compliant_count'] ?? 0) > 0 ? 'text-rose-500' : 'text-emerald-500' }}">
                        {{ $this->summary['non_compliant_count'] ?? 0 }}
                    </p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Cumplimiento</p>
                    <p class="mt-2 text-3xl font-bold text-zinc-950 dark:text-white">{{ $this->summary['compliance_percentage'] ?? 0 }}%</p>
                </div>
            </x-filament::section>
        </div>

        @php
            $healthStatus = $this->summary['health_status'] ?? 'unknown';
        @endphp

        @if ($healthStatus === 'healthy')
            <x-filament::section class="border-emerald-500/20 bg-emerald-500/10">
                <h3 class="text-lg font-semibold text-emerald-400">Estado saludable</h3>
                <p class="mt-1 text-sm text-emerald-200/80">
                    Todos los eventos revisados cumplen la regla arquitectonica.
                </p>
            </x-filament::section>
        @elseif ($healthStatus === 'warning')
            <x-filament::section class="border-amber-500/20 bg-amber-500/10">
                <h3 class="text-lg font-semibold text-amber-400">Atencion requerida</h3>
                <p class="mt-1 text-sm text-amber-200/80">
                    Hay eventos con observaciones. Conviene revisarlos antes de seguir creciendo la base de codigo.
                </p>
            </x-filament::section>
        @else
            <x-filament::section class="border-rose-500/20 bg-rose-500/10">
                <h3 class="text-lg font-semibold text-rose-400">Sin diagnostico estable</h3>
                <p class="mt-1 text-sm text-rose-200/80">
                    No se pudo construir un reporte confiable o existen violaciones criticas pendientes.
                </p>
            </x-filament::section>
        @endif

        @if (! empty($this->violations))
            <x-filament::section>
                <x-slot name="heading">Observaciones encontradas</x-slot>

                <div class="space-y-3">
                    @foreach ($this->violations as $violation)
                        <div class="rounded-2xl border px-4 py-3 {{ ($violation['severity'] ?? 'warning') === 'critical' ? 'border-rose-500/20 bg-rose-500/10' : 'border-amber-500/20 bg-amber-500/10' }}">
                            <p class="font-semibold text-white">{{ $violation['event'] ?? 'Evento' }}</p>
                            <p class="mt-1 text-sm text-zinc-300">
                                {{ $violation['rule'] ?? 'Regla' }}: {{ $violation['message'] ?? 'Sin detalle' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Detalle por evento</x-slot>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-white/10 text-left text-zinc-400">
                            <th class="px-3 py-3">Evento</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3">Reglas afectadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->events as $class => $compliance)
                            <tr class="border-b border-white/5">
                                <td class="px-3 py-3 font-mono text-xs text-zinc-300">{{ class_basename((string) $class) }}</td>
                                <td class="px-3 py-3">
                                    @if (($compliance['is_compliant'] ?? false) === true)
                                        <x-filament::badge color="success">Cumple</x-filament::badge>
                                    @else
                                        <x-filament::badge color="danger">
                                            {{ $compliance['violation_count'] ?? 0 }} observaciones
                                        </x-filament::badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-zinc-400">
                                    {{ implode(', ', array_keys($compliance['violations'] ?? [])) ?: 'Sin incidencias' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8">
                                    @include('filament.app.components.table-empty-state', [
                                        'heading' => 'Todavia no hay datos para mostrar',
                                        'description' => 'Cuando el analizador tenga eventos procesados o reglas activas, el detalle aparecera aqui.',
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            Ultima revision: {{ $this->summary['last_checked_at'] ?? now()->format('Y-m-d H:i:s') }}
        </p>
    </div>
</x-filament-panels::page>
