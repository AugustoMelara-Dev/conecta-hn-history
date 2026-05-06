{{--
    PAYMENT-PENDING - Instrucciones de Pago PRO
    
    Página mostrada después del registro PRO.
    Muestra:
    - Referencia de pago única
    - Instrucciones de transferencia bancaria
    - Botón de WhatsApp para enviar comprobante
--}}

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-12 px-4">
    <div class="max-w-xl mx-auto">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                Conecta<span class="text-blue-600">HN</span>
            </h1>
        </div>

        {{-- Card Principal --}}
        <div class="bg-white rounded-3xl shadow-xl overflow-hidden">
            {{-- Header con Icono --}}
            <div class="bg-gradient-to-r from-amber-400 to-amber-500 p-8 text-center">
                <div class="w-20 h-20 mx-auto bg-white rounded-full flex items-center justify-center mb-4 shadow-lg">
                    <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-black mb-2">¡Ya casi está listo!</h2>
                <p class="text-black/70">Completa el pago para activar tu Plan PRO</p>
            </div>

            <div class="p-8">
                {{-- Resumen del Pago --}}
                <div class="bg-gray-50 rounded-2xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-gray-600">Negocio</span>
                        <span class="font-bold text-gray-900">{{ $payment->tenant?->name ?? 'Tu Negocio' }}</span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-gray-600">Plan</span>
                        <span class="font-bold text-blue-600">PRO ⭐</span>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-gray-600">Monto</span>
                        <span class="text-2xl font-bold text-gray-900">L. {{ number_format($payment->amount, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <span class="text-gray-600">Referencia</span>
                        <span class="font-mono text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded-lg">
                            {{ $payment->reference_number }}
                        </span>
                    </div>
                </div>

                {{-- Instrucciones de Pago --}}
                @if($bankInstructions)
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">Instrucciones de Pago</h3>
                    <div class="prose prose-sm text-gray-600 bg-blue-50 rounded-xl p-4 border border-blue-100">
                        {!! nl2br(e($bankInstructions)) !!}
                    </div>
                </div>
                @else
                <div class="mb-6 bg-blue-50 rounded-xl p-4 border border-blue-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Transferencia Bancaria</h3>
                    <p class="text-gray-600 text-sm">
                        Realiza una transferencia al número de cuenta proporcionado y envía tu comprobante por WhatsApp.
                    </p>
                </div>
                @endif

                {{-- Importante --}}
                <div class="bg-amber-50 rounded-xl p-4 mb-6 border border-amber-200">
                    <div class="flex gap-3">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <div>
                            <p class="text-amber-800 font-medium text-sm">Importante</p>
                            <p class="text-amber-700 text-sm">
                                Incluye tu referencia <strong>{{ $payment->reference_number }}</strong> en la descripción de la transferencia para identificar tu pago más rápido.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Botón WhatsApp --}}
                <a 
                    href="{{ $whatsappUrl }}"
                    target="_blank"
                    class="flex items-center justify-center gap-3 w-full py-4 bg-[#25D366] hover:bg-[#20BD5A] text-white font-bold rounded-xl transition-colors shadow-lg hover:shadow-xl"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    Enviar Comprobante por WhatsApp
                </a>

                {{-- Link Secundario --}}
                <p class="text-center text-gray-500 text-sm mt-6">
                    ¿Ya realizaste el pago? Te notificaremos por correo cuando sea confirmado.
                </p>

                <div class="text-center mt-4">
                    <a href="/" class="text-blue-600 hover:underline font-medium text-sm">
                        Volver al Inicio
                    </a>
                </div>
            </div>
        </div>

        {{-- Ayuda --}}
        <p class="text-center text-gray-400 text-sm mt-8">
            ¿Tienes dudas? Escríbenos al WhatsApp de soporte.
        </p>
    </div>
</div>
