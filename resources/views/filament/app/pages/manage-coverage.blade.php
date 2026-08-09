<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6" data-testid="coverage-form">
        <x-filament::section>
            <x-slot name="heading">Dónde y cómo atiendes</x-slot>
            <x-slot name="description">La cobertura usa las ubicaciones y localidades verificadas de tu negocio. En local y recoger siempre se limitan a la localidad; remoto siempre cubre el país.</x-slot>
            {{ $this->form }}
        </x-filament::section>

        <x-filament::button type="submit" icon="heroicon-o-check">
            Guardar cobertura
        </x-filament::button>
    </form>
</x-filament-panels::page>
