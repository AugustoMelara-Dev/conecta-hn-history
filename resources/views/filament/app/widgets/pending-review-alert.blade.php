@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $status = $tenant->status;
    $isRejected = $status === 'rejected';
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        class="{{ $isRejected ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }}"
    >
        <div class="flex items-start gap-4">
            {{-- Icon --}}
            <div class="p-2 rounded-full {{ $isRejected ? 'bg-red-200 text-red-600' : 'bg-amber-100 text-amber-600 animate-pulse' }}">
                @if($isRejected)
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                @else
                    <x-heroicon-o-clock class="w-6 h-6" />
                @endif
            </div>

            {{-- Content --}}
            <div class="flex-1">
                <h2 class="text-lg font-bold {{ $isRejected ? 'text-red-800' : 'text-amber-800' }}">
                    @if($isRejected)
                        Atención: Tu solicitud requiere cambios
                    @else
                        Tu negocio está en proceso de verificación
                    @endif
                </h2>
                
                <p class="mt-2 text-sm {{ $isRejected ? 'text-red-700 w-full' : 'text-amber-700 w-full' }} leading-relaxed">
                    @if($isRejected)
                        Hemos detectado un problema con tu perfil. Por favor revisa los detalles o contacta a soporte para resolverlo.
                    @else
                        Bienvenido a Conecta HN. Para garantizar la seguridad de la comunidad, nuestro equipo está validando tu información. 
                        Mientras tanto, puedes configurar tu catálogo y subir fotos. 
                        <strong>Tu tienda será visible al público en cuanto sea aprobada.</strong>
                    @endif
                </p>

                {{-- CTA --}}
                <div class="mt-4">
                    <a 
                        href="https://wa.me/50499999999" 
                        target="_blank"
                        class="inline-flex items-center text-sm font-medium hover:underline {{ $isRejected ? 'text-red-600 hover:text-red-800' : 'text-amber-600 hover:text-amber-800' }}"
                    >
                        ¿Necesitas ayuda urgente? Contáctanos por WhatsApp &rarr;
                    </a>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
