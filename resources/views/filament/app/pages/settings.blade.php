<x-filament-panels::page>
    <form wire:submit="save" class="fi-sc-form">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-3">
            @foreach ($this->getFormActions() as $action)
                {{ $action->livewire($this) }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>
