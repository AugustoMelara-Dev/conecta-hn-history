<x-filament-widgets::widget>
    {{-- Solo mostrar si el perfil no está completo --}}
    @if(!$isComplete)
    <x-filament::section>
        <div class="space-y-4">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Completa tu perfil
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $percentage }}% completado
                    </p>
                </div>
                
                {{-- Indicador visual --}}
                <div class="flex items-center justify-center w-16 h-16 rounded-full" 
                     style="background: conic-gradient(#3b82f6 {{ $percentage }}%, #e5e7eb {{ $percentage }}%);">
                    <div class="w-12 h-12 bg-white dark:bg-gray-800 rounded-full flex items-center justify-center">
                        <span class="text-sm font-bold text-blue-600">{{ $percentage }}%</span>
                    </div>
                </div>
            </div>

            {{-- Barra de Progreso --}}
            <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700 overflow-hidden">
                <div 
                    class="h-3 rounded-full transition-all duration-500 {{ $percentage >= 80 ? 'bg-emerald-500' : ($percentage >= 50 ? 'bg-blue-500' : 'bg-amber-500') }}"
                    style="width: {{ $percentage }}%"
                ></div>
            </div>

            {{-- Items Faltantes --}}
            @if(count($missingItems) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
                @foreach(array_slice($missingItems, 0, 4) as $item)
                <a 
                    href="{{ $item['url'] }}"
                    class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                >
                    <span class="text-2xl">{{ $item['icon'] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ $item['action'] }}
                        </p>
                        <p class="text-xs text-gray-500">+{{ round(100 / 7) }}% al completar</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                @endforeach
            </div>
            
            @if(count($missingItems) > 4)
            <p class="text-center text-sm text-gray-500">
                y {{ count($missingItems) - 4 }} más...
            </p>
            @endif
            @endif
        </div>
    </x-filament::section>
    @else
    {{-- Perfil Completo - Mensaje de Éxito --}}
    <x-filament::section>
        <div class="flex items-center gap-4 p-2">
            <div class="flex items-center justify-center w-12 h-12 bg-emerald-100 dark:bg-emerald-900/50 rounded-full">
                <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">¡Perfil completo!</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tu negocio está listo para recibir clientes.</p>
            </div>
        </div>
    </x-filament::section>
    @endif
</x-filament-widgets::widget>
