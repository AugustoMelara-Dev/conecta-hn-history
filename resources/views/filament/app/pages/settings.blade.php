<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Perfil del negocio</p>
                    <h1 class="biz-title">Configuración esencial</h1>
                    <p class="biz-copy">
                        Actualiza los datos que usa tu panel y también la información base que ve el cliente en la página pública.
                    </p>
                </div>

                <form wire:submit="save" class="biz-stack">
                    {{ $this->form }}

                    <div class="biz-form-actions">
                        @foreach ($this->getFormActions() as $action)
                            {{ $action->livewire($this) }}
                        @endforeach
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
