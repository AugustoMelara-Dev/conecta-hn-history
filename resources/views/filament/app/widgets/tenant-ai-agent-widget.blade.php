<x-filament-widgets::widget>
    <x-filament::section data-testid="tenant-ai-agent-widget">
        <x-slot name="heading">
            Agente IA del negocio
        </x-slot>

        <x-slot name="description">
            IA privada para consultar ventas, reservas, catalogo y metricas del tenant actual. Apagada por defecto y protegida por PRO, allowlist y permiso.
        </x-slot>

        <div class="biz-stack">
            <div class="biz-split">
                <div class="biz-stack">
                    <div class="biz-button-row">
                        <x-filament::badge :color="$this->getStatusBadgeColor()" size="lg">
                            {{ $this->getStatusLabel() }}
                        </x-filament::badge>
                        <x-filament::badge color="gray" size="lg">
                            Sin datos cross-tenant
                        </x-filament::badge>
                    </div>

                    <div>
                        <p class="biz-kicker">Valor PRO con activacion segura</p>
                        <p class="biz-heading">Pregunta cosas como "cuanto vendi hoy" o "que reservas vienen".</p>
                        <p class="biz-muted">{{ $this->getBlockerCopy() }}</p>
                    </div>
                </div>

                @if (! $this->canUseAgent)
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-up-right"
                        href="{{ $this->upgradeUrl }}"
                    >
                        Ver PRO y activacion
                    </x-filament::button>
                @endif
            </div>

            @if ($this->canUseAgent)
                <div
                    class="biz-card"
                    x-data="{
                        message: 'Cuanto vendi hoy?',
                        answer: '',
                        loading: false,
                        error: '',
                        async ask() {
                            this.loading = true;
                            this.error = '';
                            this.answer = '';

                            try {
                                const response = await fetch('{{ $this->agentEndpoint }}', {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
                                        'X-Tenant-ID': '{{ $this->tenantKey }}',
                                    },
                                    body: JSON.stringify({ message: this.message }),
                                });
                                const payload = await response.json();

                                if (! response.ok) {
                                    throw new Error(payload.message || 'No se pudo consultar el agente.');
                                }

                                this.answer = payload.answer || 'El agente respondio sin datos suficientes.';
                            } catch (error) {
                                this.error = error.message || 'No se pudo consultar el agente.';
                            } finally {
                                this.loading = false;
                            }
                        },
                    }"
                    data-testid="tenant-ai-agent-chat"
                >
                    <div class="biz-stack">
                        <label class="biz-action-title" for="tenant-ai-agent-message">Pregunta segura</label>
                        <div class="biz-button-row">
                            <input
                                id="tenant-ai-agent-message"
                                type="text"
                                class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                x-model="message"
                                placeholder="Ej. Que reservas tengo hoy?"
                            />
                            <x-filament::button type="button" color="primary" x-on:click="ask()" x-bind:disabled="loading || message.trim().length < 3">
                                Consultar
                            </x-filament::button>
                        </div>

                        <p class="biz-note">Endpoint protegido: auth, ownership, X-Tenant-ID, ai.use, allowlist y rate limit.</p>

                        <template x-if="answer">
                            <div class="biz-empty-state" data-testid="tenant-ai-agent-answer">
                                <p class="biz-action-copy" x-text="answer"></p>
                            </div>
                        </template>

                        <template x-if="error">
                            <div class="biz-card-warning" role="alert" data-testid="tenant-ai-agent-error">
                                <p class="biz-action-copy" x-text="error"></p>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <div class="biz-actions">
                    <div class="biz-action">
                        <p class="biz-action-title">Free valida el negocio</p>
                        <p class="biz-action-copy">Micrositio, directorio, contacto y hasta 10 productos para aprender antes de pagar.</p>
                    </div>
                    <div class="biz-action">
                        <p class="biz-action-title">PRO opera con mas control</p>
                        <p class="biz-action-copy">Mas catalogo, reservas avanzadas, reportes/campanas e IA privada activable por tenant.</p>
                    </div>
                    <div class="biz-action">
                        <p class="biz-action-title">Fail closed</p>
                        <p class="biz-action-copy">Si falta allowlist, permiso o proveedor futuro, no hay 500 ni fuga de datos privados.</p>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
