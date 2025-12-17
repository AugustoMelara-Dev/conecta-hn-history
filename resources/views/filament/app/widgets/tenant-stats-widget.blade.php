<x-filament::widget>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- BENTO GRID - Dashboard de Estadísticas con Growth Hacking --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TARJETA 1: VISITAS TOTALES (Validación de Valor) --}}
        {{-- Fuente: $tenant->views (contador atómico) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 relative overflow-hidden group hover:shadow-md transition-all duration-300">
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Visitas Totales</p>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($views) }}</h3>
                </div>
                <div class="p-2.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-xl group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
            </div>
            
            {{-- Decoración de fondo --}}
            <div class="absolute -bottom-4 -right-4 w-20 h-20 bg-blue-50 dark:bg-blue-500/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            
            <div class="mt-3 flex items-center text-sm">
                <span class="text-emerald-600 dark:text-emerald-400 flex items-center font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Tráfico Real
                </span>
                <span class="text-gray-400 dark:text-gray-500 ml-2 text-xs">potenciales clientes</span>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TARJETA 2: CLICS EN WHATSAPP (Conversión) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 relative overflow-hidden group hover:shadow-md transition-all duration-300">
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Clics en WhatsApp</p>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($whatsappClicks) }}</h3>
                </div>
                <div class="p-2.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-300">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                </div>
            </div>
            
            <div class="absolute -bottom-4 -right-4 w-20 h-20 bg-emerald-50 dark:bg-emerald-500/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            
            <div class="mt-3 text-sm">
                @if($views > 0)
                    <span class="text-gray-500 dark:text-gray-400">
                        Tasa: <span class="font-bold text-gray-900 dark:text-white">{{ $this->getConversionRate() }}%</span>
                    </span>
                @else
                    <span class="text-gray-400 dark:text-gray-500">Intenciones de compra</span>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TARJETA 3: PRODUCTOS ACTIVOS (Presión en FREE) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 relative overflow-hidden group hover:shadow-md transition-all duration-300">
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Productos Activos</p>
                    <h3 class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($productsCount) }}</h3>
                </div>
                <div class="p-2.5 bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-xl group-hover:bg-purple-600 group-hover:text-white transition-colors duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
            
            <div class="absolute -bottom-4 -right-4 w-20 h-20 bg-purple-50 dark:bg-purple-500/10 rounded-full opacity-50 group-hover:scale-150 transition-transform duration-500"></div>
            
            <div class="mt-3 text-sm">
                @if(!$isPro && $productsCount >= $productLimit)
                    {{-- Alerta de límite alcanzado (Growth Hacking) --}}
                    <a href="{{ $this->getUpgradeUrl() }}" class="text-purple-600 dark:text-purple-400 hover:text-purple-700 font-bold text-xs flex items-center animate-pulse">
                        ⚠️ Límite alcanzado ({{ $productLimit }})
                        <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                @elseif(!$isPro)
                    <span class="text-gray-400 dark:text-gray-500">{{ $productsCount }}/{{ $productLimit }} disponibles</span>
                @else
                    <span class="text-gray-400 dark:text-gray-500">Catálogo ilimitado</span>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- TARJETA 4: ESTADO DEL PLAN (El Vendedor) --}}
        {{-- Psicología: Contraste visual FREE (urgencia) vs PRO (estatus) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="relative overflow-hidden rounded-xl p-5 shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg
                    {{ $isPro 
                        ? 'bg-gradient-to-br from-gray-900 to-gray-800 dark:from-gray-800 dark:to-gray-900' 
                        : 'bg-gradient-to-br from-blue-600 to-indigo-700' 
                    }} text-white">
            
            <div class="relative z-10">
                <p class="text-sm font-medium text-white/80">Tu Suscripción</p>
                <div class="flex items-center gap-2 mt-1">
                    <h3 class="text-2xl font-bold">{{ $planName }}</h3>
                    @if($isPro)
                        <span class="bg-gradient-to-r from-amber-400 to-yellow-500 text-black text-[10px] font-black tracking-wider px-2 py-0.5 rounded-full uppercase shadow-lg">
                            ⭐ ACTIVO
                        </span>
                    @endif
                </div>

                @if(!$isPro)
                    {{-- Copywriting FOMO para FREE --}}
                    <p class="text-xs text-blue-100 mt-2 mb-3 leading-relaxed">
                        Los negocios PRO aparecen primero y tienen más herramientas.
                    </p>
                    <a 
                        href="{{ $this->getUpgradeUrl() }}" 
                        class="inline-flex items-center justify-center w-full gap-2 py-2 bg-white text-blue-700 rounded-lg text-sm font-bold hover:bg-blue-50 transition-colors shadow-lg"
                    >
                        🚀 Activar Plan PRO
                    </a>
                @else
                    {{-- Mensaje tranquilizador para PRO --}}
                    <p class="text-xs text-gray-400 mt-2">
                        Todas las funciones desbloqueadas.
                    </p>
                @endif
            </div>
            
            {{-- Decoración brillante --}}
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-28 h-28 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -mb-8 -ml-8 w-24 h-24 bg-white/5 rounded-full blur-xl pointer-events-none"></div>
        </div>

    </div>
</x-filament::widget>
