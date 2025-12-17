<x-filament-widgets::widget>
    <div class="rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 p-4 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 p-2 bg-white/20 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-lg">
                        @if(!$canCreate)
                            ¡Límite Alcanzado!
                        @else
                            ¡Casi llegas al límite!
                        @endif
                    </h3>
                    <p class="text-white/90 text-sm">
                        Tienes <strong>{{ $count }}/{{ $limit }}</strong> productos. 
                        @if(!$canCreate)
                            Mejora a PRO para añadir más.
                        @else
                            Actualiza para productos ilimitados.
                        @endif
                    </p>
                </div>
            </div>
            
            <a 
                href="/admin/billing"
                class="flex items-center justify-center gap-2 px-5 py-2.5 
                       bg-white text-amber-600 font-bold rounded-lg
                       hover:bg-amber-50 transition-colors
                       whitespace-nowrap"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                </svg>
                Mejorar a PRO
            </a>
        </div>
        
        {{-- Barra de progreso --}}
        <div class="mt-4">
            <div class="w-full bg-white/30 rounded-full h-2 overflow-hidden">
                <div 
                    class="h-2 rounded-full transition-all duration-500 {{ $percentage >= 100 ? 'bg-red-400' : 'bg-white' }}"
                    style="width: {{ min($percentage, 100) }}%"
                ></div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
