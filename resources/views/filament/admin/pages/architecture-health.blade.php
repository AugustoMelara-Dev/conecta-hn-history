<x-filament-panels::page>
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Operación</p>
            <h1 class="biz-title">Salud de arquitectura</h1>
            <p class="biz-copy">Estado reportado por los checks internos de la aplicación.</p>
        </section>

        <section class="biz-list" aria-label="Checks de salud">
            @forelse ($this->getHealthResults() as $result)
                <article class="biz-checklist-item">
                    <span class="biz-status-dot is-{{ $result['status'] }}" aria-hidden="true"></span>
                    <div>
                        <h2 class="biz-checklist-title">{{ $result['name'] }}</h2>
                        <p class="biz-checklist-copy">{{ $result['message'] !== '' ? $result['message'] : ucfirst($result['status']) }}</p>
                    </div>
                </article>
            @empty
                <div class="biz-empty-state">No se recibieron resultados de salud.</div>
            @endforelse
        </section>
    </main>
</x-filament-panels::page>
