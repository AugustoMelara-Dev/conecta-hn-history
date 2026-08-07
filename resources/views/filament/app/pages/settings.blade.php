<x-filament-panels::page>
    <form wire:submit="save" data-testid="panel-settings-page">
        {{ $this->form }}

        <x-filament::actions
            :actions="$this->getFormActions()"
        />
    </form>
</x-filament-panels::page>
