@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $status = $tenant->status;
    $isRejected = $status === 'rejected';
@endphp

<x-filament-widgets::widget>
    <div class="{{ $isRejected ? 'biz-card-danger' : 'biz-card-warning' }}">
        <div class="biz-split">
            <div class="biz-split" style="align-items: center;">
                <span class="biz-icon-circle {{ $isRejected ? 'is-danger' : 'is-warning' }}">
                    @if ($isRejected)
                        <x-heroicon-o-exclamation-triangle class="fi-icon" />
                    @else
                        <x-heroicon-o-clock class="fi-icon" />
                    @endif
                </span>

                <div class="biz-stack" style="gap: 0.35rem;">
                    <h2 class="biz-heading">
                        @if ($isRejected)
                            Atención: tu solicitud requiere cambios
                        @else
                            Tu negocio está en proceso de verificación
                        @endif
                    </h2>

                    <p class="biz-copy">
                        @if ($isRejected)
                            Detectamos observaciones en tu perfil. Revisa los detalles del negocio o contacta a soporte para corregirlo.
                        @else
                            El equipo está validando tu información. Mientras tanto puedes configurar catálogo, fotos y perfil sin perder avance.
                        @endif
                    </p>
                </div>
            </div>

            <div class="biz-button-row">
                <a href="https://wa.me/50499999999" target="_blank" class="biz-button-secondary">
                    Contactar soporte
                </a>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
