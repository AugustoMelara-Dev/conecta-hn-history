<x-filament-widgets::widget>
    <x-filament::section data-testid="dashboard-product-limit-warning">
        <x-slot name="heading">
            Catalogo cerca del limite
        </x-slot>

        <x-slot name="description">
            Tienes {{ $count }}/{{ $limit }} productos en el plan actual.
        </x-slot>

        <div class="biz-stack">
            <div class="biz-split">
                <div>
                    <p class="biz-action-title">
                        {{ ! $canCreate ? 'Ya alcanzaste el limite de productos.' : 'Estas usando la mayor parte del catalogo disponible.' }}
                    </p>
                    <p class="biz-muted">
                        {{ ! $canCreate ? 'Para publicar mas productos, revisa PRO o ajusta el catalogo actual.' : 'Puedes planificar el siguiente paso antes de quedarte sin espacios.' }}
                    </p>
                </div>

                <div class="biz-button-row">
                    <x-filament::badge color="{{ ! $canCreate ? 'danger' : 'warning' }}" size="lg">
                        {{ min($percentage, 100) }}% usado
                    </x-filament::badge>

                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-arrow-up-right"
                        href="{{ route('filament.app.pages.manage-billing', ['tenant' => filament()->getTenant()?->slug]) }}"
                    >
                        Ver opciones
                    </x-filament::button>
                </div>
            </div>

            <div
                class="biz-progress"
                role="progressbar"
                aria-label="Uso del limite de productos"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ min(100, $percentage) }}"
            >
                <div
                    class="biz-progress-bar {{ $percentage >= 100 ? 'biz-progress-bar-danger' : 'biz-progress-bar-warning' }}"
                    style="width: {{ max(6, min($percentage, 100)) }}%;"
                ></div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
