<x-filament-panels::page>
    @include('filament.components.panel-critical-styles')

    <div class="mx-auto max-w-6xl space-y-6">
        <x-filament::section>
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-zinc-500 dark:text-zinc-400">Superadmin</p>
                <h1 class="text-3xl font-black tracking-tight text-zinc-950 dark:text-white">Configuracion general</h1>
                <p class="max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    Ajusta precios, soporte, pagos y controles globales sin tocar codigo. Todo lo que cambies aqui afecta a la plataforma completa.
                </p>
            </div>
        </x-filament::section>

        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" size="lg">
                    Guardar cambios
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
