<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-stack">
                <div>
                    <p class="biz-kicker">Perfil del negocio</p>
                    <h1 class="biz-title">Configuracion esencial</h1>
                    <p class="biz-copy">
                        Actualiza los datos que usa tu panel y tambien la informacion base que ve el cliente en la pagina publica.
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
