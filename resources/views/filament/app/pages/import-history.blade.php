<x-filament-panels::page>
    <div class="space-y-4" data-testid="operational-import-history">
        @forelse($this->getRecentImports() as $batch)
            <x-filament::section>
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ $batch['file'] ?: $batch['kind'] }}</p>
                            <x-filament::badge>{{ $batch['status'] }}</x-filament::badge>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $batch['kind'] }} · {{ $batch['total'] }} filas · {{ $batch['failed'] }} fallidas · {{ $batch['created_at'] }}
                        </p>
                    </div>

                    @if($batch['status'] === 'needs_review' && $batch['items'] > $batch['failed'])
                        <x-filament::button
                            type="button"
                            color="success"
                            wire:click="approveAndPublish('{{ $batch['id'] }}')"
                            wire:confirm="¿Aprobar y publicar todas las filas válidas de este lote?"
                        >
                            Aprobar y publicar
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-500">Todavía no hay importaciones.</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
