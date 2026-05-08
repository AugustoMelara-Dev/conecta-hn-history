@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $status = $tenant?->status;
    $isRejected = $status === 'rejected';
    $profileUrl = $tenant
        ? route('filament.app.tenant.profile', ['tenant' => $tenant->slug])
        : url('/app');
    $settingsUrl = $tenant
        ? route('filament.app.pages.settings', ['tenant' => $tenant->slug])
        : url('/app');
@endphp

<x-filament::widget>
    <x-filament::section class="{{ $isRejected ? 'biz-card-warning' : '' }}">
        <div class="biz-split">
            <div class="biz-stack">
                <div class="biz-button-row">
                    <x-filament::badge color="{{ $isRejected ? 'danger' : 'warning' }}" size="lg">
                        {{ $isRejected ? 'Requiere cambios' : 'En verificacion' }}
                    </x-filament::badge>
                </div>

                <div>
                    <h2 class="biz-heading">
                        @if($isRejected)
                            Tu solicitud requiere cambios
                        @else
                            Tu negocio esta en proceso de verificacion
                        @endif
                    </h2>

                    <p class="biz-muted">
                        @if($isRejected)
                            Revisa los datos del perfil del negocio para resolver la observacion y volver a enviarlo.
                        @else
                            Nuestro equipo esta validando tu informacion. Mientras tanto, puedes configurar tu catalogo y subir fotos. Tu tienda sera visible al publico cuando sea aprobada.
                        @endif
                    </p>
                </div>
            </div>

            <div class="biz-button-row">
                <x-filament::button
                    tag="a"
                    color="{{ $isRejected ? 'danger' : 'warning' }}"
                    icon="{{ $isRejected ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock' }}"
                    href="{{ $profileUrl }}"
                >
                    {{ $isRejected ? 'Revisar perfil' : 'Completar perfil' }}
                </x-filament::button>

                @if($isRejected)
                    <x-filament::button
                        tag="a"
                        color="gray"
                        icon="heroicon-o-adjustments-horizontal"
                        href="{{ $settingsUrl }}"
                    >
                        Ver configuracion
                    </x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament::widget>
