<x-filament-panels::page>
    <div class="biz-shell">
        <div class="biz-card">
            <div class="biz-grid-two">
                <div class="biz-stack">
                    <div>
                        <p class="biz-kicker">IA privada del negocio</p>
                        <h1 class="biz-title">Copiloto que lee tus datos, no el directorio publico</h1>
                        <p class="biz-copy">
                            Pregunta por ventas, clientes, pedidos, catalogo, servicios y activacion. Las fuentes se filtran por tenant y quedan auditadas.
                        </p>
                    </div>

                    <form wire:submit="ask" class="biz-stack">
                        <textarea
                            wire:model.defer="question"
                            rows="5"
                            class="fi-input block w-full rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white shadow-sm outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30"
                            placeholder="Ej: Que productos deberia empujar hoy con lo que tengo configurado?"
                        ></textarea>

                        @error('question')
                            <p class="biz-checklist-copy text-rose-300">{{ $message }}</p>
                        @enderror

                        <div class="biz-button-row">
                            <button type="submit" class="biz-button" wire:loading.attr="disabled" wire:target="ask">
                                Preguntar al copiloto
                            </button>
                            <button type="button" class="biz-button-secondary" wire:click="reindex" wire:loading.attr="disabled" wire:target="reindex">
                                Reindexar conocimiento
                            </button>
                        </div>
                    </form>
                </div>

                <div class="biz-card-accent">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Preguntas utiles</p>
                            <h2 class="biz-heading">Empieza por decisiones operativas</h2>
                            <p class="biz-muted">
                                Evita preguntas genericas. El valor esta en convertir datos privados en siguiente accion.
                            </p>
                        </div>

                        <div class="biz-actions">
                            @foreach ($suggestedQuestions as $suggestion)
                                <button type="button" class="biz-action text-left" wire:click="useSuggestion(@js($suggestion))">
                                    <p class="biz-action-title">{{ $suggestion }}</p>
                                    <p class="biz-action-copy">Usar esta pregunta</p>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($diagnosticState)
            <div class="biz-card">
                <div class="biz-grid-three">
                    <div class="biz-stat">
                        <p class="biz-stat-label">Estado</p>
                        <p class="biz-stat-value" style="font-size: 1.2rem;">{{ $diagnosticState }}</p>
                    </div>
                    <div class="biz-stat">
                        <p class="biz-stat-label">Causa probable</p>
                        <p class="biz-stat-copy">{{ $probableCause }}</p>
                    </div>
                    <div class="biz-stat">
                        <p class="biz-stat-label">Accion recomendada</p>
                        <p class="biz-stat-copy">{{ $recommendedAction }}</p>
                    </div>
                </div>

                @if ($traceId)
                    <p class="biz-muted" style="margin-top: 1rem;">Referencia soporte: <code>{{ $traceId }}</code></p>
                @endif
            </div>
        @endif

        @if ($answer)
            <div class="biz-grid-two">
                <div class="biz-card">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Respuesta</p>
                            <h2 class="biz-heading">Lectura privada del negocio</h2>
                        </div>

                        <div class="biz-stat">
                            <p class="biz-stat-copy whitespace-pre-line">{{ $answer }}</p>
                        </div>
                    </div>
                </div>

                <div class="biz-card">
                    <div class="biz-stack">
                        <div>
                            <p class="biz-kicker">Fuentes</p>
                            <h2 class="biz-heading">Evidencia usada</h2>
                            <p class="biz-muted">
                                Si una respuesta no trae fuentes, no la trates como decision final.
                            </p>
                        </div>

                        <div class="biz-list">
                            @forelse ($sources as $source)
                                <div class="biz-checklist-item">
                                    <div class="biz-checklist-index is-complete">
                                        {{ strtoupper(substr((string) ($source['retrieval_mode'] ?? 'src'), 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="biz-checklist-title">{{ $source['title'] ?? 'Fuente privada' }}</p>
                                        <p class="biz-checklist-copy">{{ $source['excerpt'] ?? '' }}</p>
                                        <p class="biz-muted">Tipo: {{ $source['source_type'] ?? 'n/a' }} | Score: {{ $source['score'] ?? 'n/a' }}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="biz-empty-state">
                                    <p class="biz-muted">No hay fuentes privadas para esta respuesta.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
