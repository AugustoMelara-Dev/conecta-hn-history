<x-filament-panels::page>
    @php($stats = $this->getStats())
    <div class="grid gap-4 md:grid-cols-4" data-testid="automation-dashboard">
        @foreach([
            'Workflows' => $stats['total_workflows'],
            'Publicados' => $stats['active'],
            'Ejecuciones hoy' => $stats['runs_today'],
            'Tasa de éxito' => $stats['success_rate'].'%',
        ] as $label => $value)
            <x-filament::section>
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ $value }}</p>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section>
        <x-slot name="heading">Workflows con más ejecuciones</x-slot>
        <div class="divide-y divide-gray-200 dark:divide-white/10">
            @forelse($this->getTopWorkflows() as $workflow)
                <div class="flex items-center justify-between gap-4 py-3">
                    <span class="font-medium">{{ $workflow->workflow_key }}</span>
                    <x-filament::badge>{{ $workflow->executions }} ejecuciones</x-filament::badge>
                </div>
            @empty
                <p class="py-6 text-sm text-gray-500">Todavía no hay ejecuciones registradas.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
