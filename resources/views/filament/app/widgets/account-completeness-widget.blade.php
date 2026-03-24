<x-filament-widgets::widget>
    <x-filament::section>
        @if (! $isComplete)
            <div class="biz-stack">
                <div class="biz-split">
                    <div class="biz-stack" style="gap: 0.35rem;">
                        <h3 class="biz-heading">Completa tu perfil</h3>
                        <p class="biz-muted">
                            {{ count($missingItems) }} elementos pendientes de {{ max($totalChecks, 1) }}.
                        </p>
                    </div>

                    <div class="biz-badge biz-badge-success">{{ $percentage }}%</div>
                </div>

                <div class="biz-progress">
                    <div
                        class="biz-progress-bar"
                        style="width: {{ max(0, min($percentage, 100)) }}%;"
                    ></div>
                </div>

                @if (count($missingItems) > 0)
                    <div class="biz-grid-auto">
                        @foreach (array_slice($missingItems, 0, 4) as $item)
                            <a href="{{ $item['url'] }}" class="biz-action">
                                <div class="biz-stack" style="gap: 0.5rem;">
                                    <div class="biz-split">
                                        <p class="biz-action-title">{{ $item['field'] }}</p>
                                        <span class="biz-badge biz-badge-warning">Pendiente</span>
                                    </div>

                                    <p class="biz-action-copy">{{ $item['action'] }}</p>
                                    <p class="biz-note">+{{ round(100 / max($totalChecks, 1)) }}% al completar</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="biz-split">
                <div class="biz-split" style="align-items: center;">
                    <span class="biz-icon-circle is-success">
                        <x-heroicon-o-check class="fi-icon" />
                    </span>

                    <div class="biz-stack" style="gap: 0.25rem;">
                        <h3 class="biz-heading">Perfil completo</h3>
                        <p class="biz-muted">Tu negocio ya está listo para recibir clientes.</p>
                    </div>
                </div>

                <span class="biz-badge biz-badge-success">100%</span>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
