{{--
    DIRECTORY FILTERS - Componente de Filtros Reutilizable
    
    Usado en:
    - Sidebar desktop (sticky)
    - Drawer móvil (slide-over)
--}}

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- FILTRO: CIUDADES --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
    <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
        </svg>
        Ciudad
    </h3>
    <select 
        wire:model.live="city"
        class="w-full border-gray-200 rounded-xl text-sm focus:ring-blue-500 focus:border-blue-500"
    >
        <option value="">Todas las ciudades</option>
        @foreach($cities as $cityOption)
        <option value="{{ $cityOption->slug }}">
            {{ $cityOption->name }} ({{ $cityOption->tenants_count }})
        </option>
        @endforeach
    </select>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- FILTRO: CATEGORÍAS (Visual con colores) --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
    <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
        </svg>
        Categoría
    </h3>
    <div class="space-y-1 max-h-64 overflow-y-auto pr-2">
        @foreach($categories as $cat)
        <button 
            wire:click="selectCategory('{{ $cat->slug }}')"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left
                   transition-all duration-200
                   {{ $category === $cat->slug 
                       ? 'ring-2 shadow-sm' 
                       : 'hover:bg-gray-50' }}"
            style="{{ $category === $cat->slug 
                ? 'background-color: ' . ($cat->color ?? '#3b82f6') . '15; ring-color: ' . ($cat->color ?? '#3b82f6') 
                : '' }}"
        >
            {{-- Indicador de Color --}}
            <span 
                class="w-3 h-3 rounded-full flex-shrink-0"
                style="background-color: {{ $cat->color ?? '#3b82f6' }}"
            ></span>
            
            {{-- Nombre y Contador --}}
            <span class="flex-1 text-sm {{ $category === $cat->slug ? 'font-medium' : 'text-gray-600' }}"
                  style="{{ $category === $cat->slug ? 'color: ' . ($cat->color ?? '#3b82f6') : '' }}"
            >
                {{ $cat->name }}
            </span>
            <span class="text-xs text-gray-400">{{ $cat->tenants_count }}</span>
            
            {{-- Check si seleccionado --}}
            @if($category === $cat->slug)
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" style="color: {{ $cat->color ?? '#3b82f6' }}">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            @endif
        </button>
        @endforeach
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- FILTRO: ABIERTO AHORA (Toggle) --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
    <label class="flex items-center justify-between cursor-pointer">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </span>
            <div>
                <p class="font-medium text-gray-900">Abierto Ahora</p>
                <p class="text-xs text-gray-500">Solo negocios operando</p>
            </div>
        </div>
        
        {{-- Toggle Switch --}}
        <button 
            wire:click="$toggle('isOpen')"
            type="button"
            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
                   {{ $isOpen ? 'bg-emerald-500' : 'bg-gray-200' }}"
            role="switch"
            aria-checked="{{ $isOpen ? 'true' : 'false' }}"
        >
            <span 
                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                       {{ $isOpen ? 'translate-x-5' : 'translate-x-0' }}"
            ></span>
        </button>
    </label>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- INFO: Estadísticas del Directorio --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-4 border border-blue-100">
    <p class="text-sm text-blue-800 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Los negocios verificados aparecen primero
    </p>
</div>
