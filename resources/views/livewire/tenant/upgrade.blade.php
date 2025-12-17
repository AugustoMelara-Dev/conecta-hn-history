{{--
    Upgrade Landing Page - Internal Sales Page
    
    Purpose: Convert FREE users to PRO via comparative pricing table.
    Flow: Dashboard → Upgrade → Payment::create → PaymentPending
--}}

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50 py-12 px-4">
    <div class="max-w-5xl mx-auto">
        
        {{-- Header --}}
        <div class="text-center mb-12">
            <a href="/app/{{ $tenant->slug }}" wire:navigate class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Volver al Dashboard
            </a>
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Lleva tu negocio al <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-blue-600">siguiente nivel</span>
            </h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Desbloquea todo el potencial de <strong>{{ $tenant->name }}</strong> con el Plan PRO
            </p>
        </div>

        {{-- Pricing Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8">
            
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- COLUMNA FREE (Anclaje Visual) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="bg-gray-50 rounded-2xl border border-gray-200 p-6 md:p-8 opacity-75">
                <div class="mb-6">
                    <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Plan Actual</span>
                    <h3 class="text-2xl font-bold text-gray-700 mt-1">Gratuito</h3>
                </div>

                {{-- Price --}}
                <div class="mb-8">
                    <div class="flex items-baseline gap-1">
                        <span class="text-4xl font-bold text-gray-600">L. 0</span>
                        <span class="text-gray-500">/ mes</span>
                    </div>
                </div>

                {{-- Features --}}
                <ul class="space-y-4 mb-8">
                    @foreach($features['free'] as $feature)
                        <li class="flex items-start gap-3">
                            @if($feature['included'])
                                <svg class="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            @else
                                <svg class="w-5 h-5 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                            <span class="{{ $feature['included'] ? 'text-gray-600' : 'text-gray-400 line-through' }}">
                                {{ $feature['text'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                {{-- Disabled Button --}}
                <button 
                    disabled
                    class="w-full py-3 px-6 bg-gray-200 text-gray-500 font-medium rounded-xl cursor-not-allowed"
                >
                    Tu plan actual
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- COLUMNA PRO (Héroe) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="bg-white rounded-2xl border-2 border-indigo-500 shadow-xl p-6 md:p-8 relative">
                {{-- Badge Recomendado --}}
                <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                    <span class="px-4 py-1.5 bg-gradient-to-r from-indigo-600 to-blue-600 text-white text-sm font-bold rounded-full shadow-lg">
                        ⭐ RECOMENDADO
                    </span>
                </div>

                <div class="mb-6 pt-2">
                    <span class="text-sm font-medium text-indigo-600 uppercase tracking-wider">Plan PRO</span>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">Profesional</h3>
                </div>

                {{-- Price --}}
                <div class="mb-8">
                    <div class="flex items-baseline gap-1">
                        <span class="text-4xl font-bold text-gray-900">L. {{ number_format($price, 0, ',', ',') }}</span>
                        <span class="text-gray-500">/ mes</span>
                    </div>
                    <p class="text-sm text-emerald-600 mt-1 font-medium">
                        ✨ Todo incluido, sin comisiones ocultas
                    </p>
                </div>

                {{-- Features --}}
                <ul class="space-y-4 mb-8">
                    @foreach($features['pro'] as $feature)
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-gray-700 font-medium">{{ $feature['text'] }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- CTA Button --}}
                <button 
                    wire:click="createUpgradeOrder"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait"
                    class="w-full py-4 px-6 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold text-lg rounded-xl shadow-lg shadow-indigo-500/25 transition-all hover:shadow-xl active:scale-[0.98]"
                >
                    <span wire:loading.remove>
                        🚀 Mejorar Plan Ahora
                    </span>
                    <span wire:loading class="flex items-center justify-center gap-2">
                        <x-spinner size="sm" />
                        Procesando orden...
                    </span>
                </button>

                {{-- Security Badge --}}
                <div class="mt-4 flex items-center justify-center gap-2 text-sm text-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span>Pago seguro</span>
                </div>
            </div>
        </div>

        {{-- Trust & Process Clarification --}}
        <div class="mt-12 text-center">
            <div class="inline-flex items-center gap-3 px-6 py-4 bg-blue-50 rounded-xl border border-blue-100">
                <svg class="w-6 h-6 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm text-blue-800 text-left">
                    <strong>¿Cómo funciona?</strong> El pago se realiza mediante transferencia bancaria. 
                    Tu cuenta se activará en <strong>menos de 24 horas</strong> tras enviar el comprobante.
                </p>
            </div>
        </div>

        {{-- FAQ Teaser --}}
        <div class="mt-8 text-center">
            <p class="text-gray-500">
                ¿Tienes dudas? 
                <a href="https://wa.me/50499999999" target="_blank" class="text-indigo-600 hover:underline font-medium">
                    Escríbenos por WhatsApp
                </a>
            </p>
        </div>
    </div>
</div>
