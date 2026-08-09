<x-filament-panels::page>
    <form wire:submit.prevent data-testid="operational-import-wizard" class="space-y-6">
        {{ $this->form }}
        <x-filament::actions :actions="$this->getFormActions()" />
    </form>
</x-filament-panels::page>
