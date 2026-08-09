<x-filament-panels::page>
    <main class="biz-shell">
        <section class="biz-card">
            <p class="biz-kicker">Auditoría</p>
            <h1 class="biz-title">Registro de incidentes</h1>
            <p class="biz-copy">Documenta incidentes operativos sin incluir secretos ni datos personales.</p>
            <form wire:submit="logIncident" class="biz-stack">
                {{ $this->form }}
                <div><x-filament::button type="submit">Registrar incidente</x-filament::button></div>
            </form>
        </section>

        <section class="biz-card" aria-labelledby="incident-history-title">
            <h2 id="incident-history-title" class="biz-heading">Incidentes recientes</h2>
            <div class="biz-list">
                @forelse ($this->getRecentIncidents() as $incident)
                    <article class="biz-checklist-item">
                        <span class="biz-badge biz-badge-warning">{{ $incident->event ?? 'sin nivel' }}</span>
                        <div>
                            <p class="biz-checklist-title">{{ $incident->description }}</p>
                            <p class="biz-checklist-copy">{{ $incident->created_at }}</p>
                        </div>
                    </article>
                @empty
                    <div class="biz-empty-state">No hay incidentes registrados.</div>
                @endforelse
            </div>
        </section>
    </main>
</x-filament-panels::page>
