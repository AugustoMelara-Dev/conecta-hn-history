<x-filament-panels::page>
    <form wire:submit="save" data-testid="panel-settings-page">
        {{ $this->form }}

        <x-filament::button type="submit">
            Guardar ajustes
        </x-filament::button>
    </form>
</x-filament-panels::page>
