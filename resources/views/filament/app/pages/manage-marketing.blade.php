<x-filament-panels::page>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- VELVET ROPE: PAYWALL PARA USUARIOS FREE --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if(!$isPro)
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="max-w-lg w-full bg-white dark:bg-gray-800 rounded-3xl shadow-2xl overflow-hidden">
            {{-- Gradiente Header --}}
            <div class="bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-700 py-10 px-8 text-center">
                <div class="text-6xl mb-4">🚀</div>
                <h2 class="text-2xl font-bold text-white mb-2">
                    Multiplica tus Ventas
                </h2>
                <p class="text-white/80">
                    Pack de Marketing Digital PRO
                </p>
            </div>

            {{-- Beneficios --}}
            <div class="p-8 space-y-5">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 flex-shrink-0 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <x-heroicon-o-qr-code class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Cartelería QR Profesional</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Descarga carteles listos para pegar en tu local. Tus clientes escanean y ven tu menú al instante.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 flex-shrink-0 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">WhatsApp Inteligente</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Configura mensajes personalizados con el nombre del producto incluido. Convierte visitantes en clientes.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 flex-shrink-0 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                        <x-heroicon-o-folder class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Secciones de Menú</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Organiza tus productos en categorías arrastrables: Entradas, Platos Fuertes, Bebidas, etc.</p>
                    </div>
                </div>

                {{-- CTA --}}
                <div class="pt-4">
                    <a 
                        href="{{ route('filament.app.pages.manage-billing', ['tenant' => filament()->getTenant()->slug]) }}"
                        class="block w-full py-4 px-6 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold text-center rounded-xl shadow-lg transition-all hover:shadow-xl hover:scale-[1.02]"
                    >
                        ✨ Activar Plan PRO - L. 99/mes
                    </a>
                    <p class="text-center text-xs text-gray-400 mt-3">
                        Cancela cuando quieras
                    </p>
                </div>
            </div>
        </div>
    </div>
    @else
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- DASHBOARD DE HERRAMIENTAS PRO --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- HERRAMIENTA 1: CONFIGURADOR DE WHATSAPP --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-green-500 to-emerald-600">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/20 rounded-lg">
                        <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="font-bold text-white">Configurador de WhatsApp</h3>
                        <p class="text-sm text-white/80">Personaliza tus mensajes automáticos</p>
                    </div>
                </div>
            </div>

            <div class="p-6 space-y-6">
                {{-- Formulario --}}
                {{ $this->form }}

                {{-- Live Preview --}}
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Vista Previa del Mensaje
                    </label>
                    
                    {{-- WhatsApp Chat Bubble --}}
                    <div class="bg-[#E5DDD5] dark:bg-gray-700 rounded-xl p-4 max-w-sm">
                        {{-- Burbuja de mensaje saliente --}}
                        <div class="flex justify-end">
                            <div class="bg-[#DCF8C6] dark:bg-green-700 rounded-lg rounded-tr-none px-3 py-2 max-w-[85%] shadow-sm relative">
                                <p class="text-gray-800 dark:text-gray-100 text-sm whitespace-pre-line">{{ $messagePreview }}</p>
                                <div class="flex items-center justify-end gap-1 mt-1">
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ now()->format('g:i A') }}</span>
                                    {{-- Doble check azul --}}
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                        Así verán el mensaje tus clientes al hacer clic en "WhatsApp"
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- HERRAMIENTA 2: GENERADOR DE CARTELERÍA QR --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-violet-500 to-purple-600">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/20 rounded-lg">
                        <x-heroicon-o-qr-code class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="font-bold text-white">Cartelería QR</h3>
                        <p class="text-sm text-white/80">Póster listo para imprimir</p>
                    </div>
                </div>
            </div>

            <div class="p-6 text-center">
                {{-- Vista previa del QR --}}
                <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-8 inline-block mb-6">
                    @if($qrCodeSvg)
                        <div class="mx-auto" style="width: 250px; height: 250px;">
                            {!! $qrCodeSvg !!}
                        </div>
                    @else
                        <div class="w-64 h-64 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                            <x-heroicon-o-qr-code class="w-24 h-24 text-gray-400" />
                        </div>
                    @endif
                </div>

                {{-- URL del negocio --}}
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    <span class="font-medium">Tu enlace:</span>
                    <a href="{{ $businessUrl }}" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                        {{ $businessUrl }}
                    </a>
                </p>

                {{-- Información del cartel --}}
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 mb-6 text-left">
                    <h4 class="font-semibold text-purple-900 dark:text-purple-300 mb-2">El cartel PDF incluye:</h4>
                    <ul class="text-sm text-purple-700 dark:text-purple-400 space-y-1">
                        <li>✓ Código QR gigante de alta resolución</li>
                        <li>✓ Nombre de tu negocio destacado</li>
                        <li>✓ Instrucciones para escanear</li>
                        <li>✓ URL en texto (backup)</li>
                        <li>✓ Tamaño carta, listo para imprimir</li>
                    </ul>
                </div>

                {{-- Botón de descarga --}}
                <button 
                    type="button"
                    wire:click="downloadPoster"
                    class="w-full py-4 px-6 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white font-bold rounded-xl shadow-lg transition-all hover:shadow-xl flex items-center justify-center gap-2"
                >
                    <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                    Descargar Cartel PDF
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HERRAMIENTA 3: GESTOR DE SECCIONES DE MENÚ --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-amber-500 to-orange-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-white/20 rounded-lg">
                    <x-heroicon-o-folder class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="font-bold text-white">Secciones de Menú</h3>
                    <p class="text-sm text-white/80">Organiza tus productos en categorías arrastrables</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- Filament Table --}}
            {{ $this->table }}
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- CONSEJOS DE USO --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="mt-6 bg-amber-50 dark:bg-amber-900/20 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <div class="p-2 bg-amber-100 dark:bg-amber-900/40 rounded-lg">
                <x-heroicon-o-light-bulb class="w-6 h-6 text-amber-600 dark:text-amber-400" />
            </div>
            <div>
                <h3 class="font-bold text-amber-900 dark:text-amber-300 mb-2">💡 Consejos de Marketing</h3>
                <ul class="text-sm text-amber-800 dark:text-amber-400 space-y-2">
                    <li><strong>En la caja registradora:</strong> Coloca el cartel QR donde los clientes esperan. Pueden ver el menú mientras hacen fila.</li>
                    <li><strong>En las mesas:</strong> Un QR en cada mesa permite a los clientes ver fotos de los platillos antes de ordenar.</li>
                    <li><strong>Mensajes de WhatsApp:</strong> Usa {producto} para saber exactamente qué quiere el cliente sin ida y vuelta.</li>
                    <li><strong>Secciones de Menú:</strong> Arrastra y suelta las secciones para definir el orden en que aparecen en tu menú público.</li>
                </ul>
            </div>
        </div>
    </div>
    @endif
</x-filament-panels::page>
