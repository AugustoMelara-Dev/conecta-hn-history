<x-filament-panels::page>
    <form wire:submit.prevent="export" data-testid="export-center" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
            Descargar exportación
        </x-filament::button>
    </form>
</x-filament-panels::page>
