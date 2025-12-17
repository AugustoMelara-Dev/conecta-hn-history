{{--
    Marketing Module - Digital Channels & Physical Assets
    
    Features:
    - WhatsApp configuration with live preview
    - QR Code visualization
    - PDF download for print materials
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">📣 Marketing</h1>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- CARD 1: WhatsApp Channel --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-green-500 rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900">Canal de WhatsApp</h2>
                        <p class="text-sm text-gray-500">Configura cómo tus clientes te contactan</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left: Configuration --}}
                    <div class="space-y-4">
                        {{-- Phone Number --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de WhatsApp</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-medium">+504</span>
                                <input
                                    wire:model.live="whatsapp_number"
                                    type="text"
                                    placeholder="9999-9999"
                                    class="w-full pl-14 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500"
                                />
                            </div>
                            @error('whatsapp_number') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Message Template --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Plantilla de Mensaje</label>
                            <textarea
                                wire:model.live="whatsapp_template"
                                rows="4"
                                placeholder="Escribe el mensaje que verán tus clientes..."
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 resize-none"
                            ></textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                💡 Usa <code class="bg-gray-100 px-1 rounded">{name}</code> para insertar el nombre de tu negocio automáticamente.
                            </p>
                            @error('whatsapp_template') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>

                        {{-- Save Button --}}
                        <button
                            wire:click="save"
                            wire:loading.attr="disabled"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-xl transition-colors"
                        >
                            <span wire:loading.remove>Guardar Configuración</span>
                            <span wire:loading>Guardando...</span>
                        </button>
                    </div>

                    {{-- Right: Live Preview --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Vista Previa</label>
                        <div class="bg-[#e5ddd5] rounded-xl p-4 min-h-[200px]">
                            {{-- WhatsApp Bubble --}}
                            <div class="max-w-[80%] ml-auto">
                                <div class="bg-[#dcf8c6] rounded-xl rounded-tr-none p-3 shadow-sm">
                                    <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $this->messagePreview }}</p>
                                    <p class="text-[10px] text-gray-500 text-right mt-1">{{ now()->format('h:i A') }}</p>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 text-center">Así verán tu mensaje los clientes</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- CARD 2: QR Code & Print Materials --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-purple-50">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-600 rounded-xl">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900">Código QR para Mesa</h2>
                        <p class="text-sm text-gray-500">Convierte visitas físicas en clientes digitales</p>
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Left: QR Preview --}}
                    <div class="flex flex-col items-center justify-center bg-gray-50 rounded-xl p-6">
                        <img 
                            src="{{ $this->qrCodeUrl }}" 
                            alt="QR Code" 
                            class="w-48 h-48 rounded-lg shadow-lg"
                        />
                        <p class="mt-4 text-sm text-gray-600 text-center">
                            <span class="font-medium">{{ $this->storefrontUrl }}</span>
                        </p>
                    </div>

                    {{-- Right: CTA --}}
                    <div class="flex flex-col justify-center">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">📦 Kit para Mesa</h3>
                        <p class="text-gray-600 mb-4">
                            Descarga un PDF listo para imprimir con tu código QR. Colócalo en mesas, mostradores o vitrinas para que tus clientes vean tu menú digital al instante.
                        </p>
                        
                        <ul class="space-y-2 mb-6">
                            <li class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Tamaño carta listo para imprimir
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Alto contraste para cualquier impresora
                            </li>
                            <li class="flex items-center gap-2 text-sm text-gray-600">
                                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Instrucciones incluidas para clientes
                            </li>
                        </ul>

                        <button
                            wire:click="downloadQrPdf"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-indigo-500/25 transition-all"
                        >
                            <svg wire:loading.remove class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            <x-spinner wire:loading size="sm" />
                            <span wire:loading.remove>Descargar PDF para Imprimir</span>
                            <span wire:loading>Generando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pro Tip --}}
        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 rounded-xl p-4 border border-amber-200">
            <div class="flex items-start gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <h3 class="font-semibold text-gray-900">Tip de Marketing</h3>
                    <p class="text-sm text-gray-600">
                        Imprime varios QR y colócalos en cada mesa. Los clientes pueden escanear con su teléfono y hacer su pedido directamente por WhatsApp, ¡sin necesidad de esperar al mesero!
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>
