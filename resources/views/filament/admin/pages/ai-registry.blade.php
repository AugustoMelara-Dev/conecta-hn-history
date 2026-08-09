<x-filament-panels::page>
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Gobierno de IA</p>
            <h1 class="biz-title">Registro de modelos</h1>
            <p class="biz-copy">Selecciona el proveedor lógico y el modelo permitido. Las credenciales permanecen fuera de esta pantalla.</p>
            <form wire:submit="save" class="biz-stack">
                {{ $this->form }}
                <div><x-filament::button type="submit">Guardar configuración</x-filament::button></div>
            </form>
        </section>

        <section class="biz-card" aria-labelledby="assistants-title">
            <h2 id="assistants-title" class="biz-heading">Asistentes registrados</h2>
            <div class="biz-actions biz-actions-grid">
                @forelse ($this->getAssistants() as $assistant)
                    <article class="biz-action">
                        <p class="biz-action-title">{{ $assistant['name'] }}</p>
                        <p class="biz-action-copy">{{ $assistant['category'] }} · costo estimado {{ $assistant['cost'] }}</p>
                    </article>
                @empty
                    <div class="biz-empty-state">No hay asistentes disponibles en el registro actual.</div>
                @endforelse
            </div>
        </section>
    </main>
</x-filament-panels::page>
