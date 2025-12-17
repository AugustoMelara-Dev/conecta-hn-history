{{--
    DIRECTORY - Marketplace de Alta Velocidad
    
    Arquitectura SPA con Glassmorphism y Deep Linking.
    - Mobile: Sticky Glass Header + Bottom Sheet Drawer
    - Desktop: Sidebar Persistente
--}}

<div 
    class="min-h-screen bg-gray-50"
    x-data="{ 
        filtersOpen: @entangle('showFiltersDrawer'),
        scrolled: false
    }"
    @scroll.window="scrolled = window.scrollY > 20"
>
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MOBILE: STICKY GLASS HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header 
        class="lg:hidden sticky top-0 z-50 transition-all duration-300"
        :class="scrolled ? 'backdrop-blur-md bg-white/90 shadow-sm border-b border-gray-100' : 'bg-white'"
    >
        <div class="px-4 py-3">
            {{-- Barra de Búsqueda --}}
            <div class="relative">
                <input 
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="¿Qué estás buscando?"
                    class="w-full h-12 pl-11 pr-4 
                           bg-gray-100 border-0 
                           rounded-xl text-gray-900
                           placeholder:text-gray-400
                           focus:ring-2 focus:ring-blue-500/30 focus:bg-white
                           transition-all"
                >
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            
            {{-- Pills de Filtro Rápido --}}
            <div class="flex items-center gap-2 mt-3 overflow-x-auto scrollbar-hide pb-1">
                {{-- Toggle "Abierto Ahora" --}}
                <button 
                    wire:click="toggleOpenNow"
                    class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap
                           transition-all duration-200
                           {{ $isOpen 
                               ? 'bg-emerald-500 text-white shadow-md' 
                               : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}"
                >
                    <span class="w-2 h-2 rounded-full {{ $isOpen ? 'bg-white animate-pulse' : 'bg-emerald-500' }}"></span>
                    Abierto
                </button>
                
                {{-- Ciudad Seleccionada o Selector --}}
                @if($city)
                <button 
                    wire:click="clearFilter('city')"
                    class="flex-shrink-0 flex items-center gap-1 px-4 py-2 rounded-full text-sm font-medium
                           bg-blue-100 text-blue-700 whitespace-nowrap"
                >
                    📍 {{ $selectedCityName }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                @endif
                
                {{-- Botón Filtros Completos --}}
                <button 
                    @click="filtersOpen = true"
                    class="flex-shrink-0 flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium
                           bg-gray-900 text-white whitespace-nowrap"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                    </svg>
                    Filtros
                    @if($activeFiltersCount > 0)
                    <span class="flex items-center justify-center w-5 h-5 text-xs font-bold bg-blue-500 rounded-full">
                        {{ $activeFiltersCount }}
                    </span>
                    @endif
                </button>
            </div>
        </div>
    </header>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- LAYOUT PRINCIPAL --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-7xl mx-auto px-4 py-6 lg:py-8">
        <div class="flex gap-8">
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- DESKTOP: SIDEBAR PERSISTENTE --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <aside class="hidden lg:block w-72 flex-shrink-0">
                <div class="sticky top-24 space-y-5">
                    {{-- Búsqueda Desktop --}}
                    <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                        <div class="relative">
                            <input 
                                type="text"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Buscar negocios..."
                                class="w-full h-11 pl-10 pr-4 
                                       bg-gray-50 border border-gray-200 
                                       rounded-xl text-sm
                                       focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500
                                       transition-all"
                            >
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    @include('livewire.pages.partials.directory-filters')
                </div>
            </aside>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- CONTENIDO PRINCIPAL --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <main class="flex-1 min-w-0">
                {{-- Breadcrumb y Contador --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            @if($category)
                                {{ $categories->firstWhere('slug', $category)?->name ?? 'Directorio' }}
                            @else
                                Directorio
                            @endif
                            @if($selectedCityName)
                                <span class="text-gray-400 font-normal">en {{ $selectedCityName }}</span>
                            @endif
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">
                            {{ number_format($tenants->total()) }} negocios encontrados
                        </p>
                    </div>
                    
                    {{-- Ordenamiento --}}
                    <select 
                        wire:model.live="sort"
                        class="w-full sm:w-auto text-sm border-gray-200 rounded-lg 
                               focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="relevance">Más relevantes</option>
                        <option value="newest">Más recientes</option>
                        <option value="name">Nombre A-Z</option>
                    </select>
                </div>

                {{-- Filtros Activos Pills --}}
                @if($activeFiltersCount > 0)
                <div class="flex flex-wrap gap-2 mb-6">
                    @if($search)
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-full">
                        "{{ Str::limit($search, 20) }}"
                        <button wire:click="clearFilter('search')" class="ml-1 hover:text-gray-900">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </span>
                    @endif
                    
                    @if($category)
                    @php $cat = $categories->firstWhere('slug', $category); @endphp
                    @if($cat)
                    <span 
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm rounded-full"
                        style="background-color: {{ $cat->color }}15; color: {{ $cat->color }}"
                    >
                        {{ $cat->name }}
                        <button wire:click="clearFilter('category')" class="ml-1 hover:opacity-70">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </span>
                    @endif
                    @endif
                    
                    @if($isOpen)
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-emerald-50 text-emerald-700 rounded-full">
                        🟢 Abierto Ahora
                        <button wire:click="clearFilter('isOpen')" class="ml-1 hover:text-emerald-900">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </span>
                    @endif
                    
                    <button 
                        wire:click="clearFilters"
                        class="text-sm text-gray-500 hover:text-blue-600 underline underline-offset-2"
                    >
                        Limpiar todo
                    </button>
                </div>
                @endif

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- GRID DE POWER CARDS --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                @if($tenants->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4 md:gap-6">
                    @foreach($tenants as $tenant)
                    @php
                        $isBusinessOpen = $this->isBusinessOpen($tenant);
                    @endphp
                    <a 
                        href="{{ route('tenant.show', $tenant->slug) }}"
                        class="group bg-white rounded-2xl overflow-hidden
                               shadow-sm hover:shadow-xl
                               transform hover:-translate-y-1 transition-all duration-300
                               {{ $tenant->is_pro ? 'ring-2 ring-yellow-400/60' : 'border border-gray-100' }}"
                    >
                        {{-- IMAGEN CINEMÁTICA (16:9) --}}
                        <div class="relative aspect-video bg-gray-100 overflow-hidden">
                            @if($tenant->banner_path)
                            <img 
                                src="{{ asset('storage/' . $tenant->banner_path) }}" 
                                alt="{{ $tenant->name }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                loading="lazy"
                            >
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center">
                                <span class="text-white/20 text-6xl font-black">{{ substr($tenant->name, 0, 1) }}</span>
                            </div>
                            @endif
                            
                            {{-- BADGE: Estado Operativo (Esquina Sup-Izq) --}}
                            <div class="absolute top-3 left-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold rounded-full shadow-lg backdrop-blur-sm
                                    {{ $isBusinessOpen 
                                        ? 'bg-emerald-500/90 text-white' 
                                        : 'bg-red-500/90 text-white' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $isBusinessOpen ? 'bg-white animate-pulse' : 'bg-white/70' }}"></span>
                                    {{ $isBusinessOpen ? 'ABIERTO' : 'CERRADO' }}
                                </span>
                            </div>
                            
                            {{-- BADGE: PRO (Esquina Sup-Der) --}}
                            @if($tenant->is_pro)
                            <div class="absolute top-3 right-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold 
                                             bg-gradient-to-r from-yellow-400 to-amber-500 text-yellow-900 
                                             rounded-full shadow-lg">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    PRO
                                </span>
                            </div>
                            @endif
                            
                            {{-- LOGO SUPERPUESTO --}}
                            <div class="absolute -bottom-5 left-4">
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white shadow-lg ring-3 ring-white">
                                    @if($tenant->logo_path)
                                    <img 
                                        src="{{ asset('storage/' . $tenant->logo_path) }}" 
                                        alt="{{ $tenant->name }}"
                                        class="w-full h-full object-cover"
                                        loading="lazy"
                                    >
                                    @else
                                    <div class="w-full h-full bg-gradient-to-br from-blue-600 to-blue-700 flex items-center justify-center text-white font-bold text-lg">
                                        {{ substr($tenant->name, 0, 1) }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        {{-- ZONA DE INFORMACIÓN --}}
                        <div class="p-4 pt-7">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-bold text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                                        {{ $tenant->name }}
                                    </h3>
                                    
                                    <div class="flex items-center gap-2 mt-1.5">
                                        {{-- Categoría --}}
                                        @if($tenant->category)
                                        <span 
                                            class="inline-block px-2 py-0.5 text-xs font-medium rounded-md"
                                            style="background-color: {{ $tenant->category->color ?? '#e5e7eb' }}12; color: {{ $tenant->category->color ?? '#6b7280' }}"
                                        >
                                            {{ $tenant->category->name }}
                                        </span>
                                        @endif
                                        
                                        {{-- Rating --}}
                                        @if($tenant->rating)
                                        <span class="flex items-center gap-0.5 text-sm text-gray-500">
                                            <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                            {{ number_format($tenant->rating, 1) }}
                                        </span>
                                        @endif
                                    </div>
                                    
                                    {{-- Ubicación --}}
                                    @if($tenant->city)
                                    <p class="flex items-center gap-1 mt-2 text-xs text-gray-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        </svg>
                                        {{ $tenant->city->name }}
                                    </p>
                                    @endif
                                </div>
                                
                                {{-- CTA Sutil --}}
                                <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="flex items-center justify-center w-9 h-9 bg-blue-600 text-white rounded-full">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- INFINITE SCROLL SENSOR (Zero-Click Pagination) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                @if($tenants->hasMorePages())
                <div 
                    x-data
                    x-intersect.full="$wire.loadMore()"
                    class="pt-6"
                >
                    {{-- ══════════════════════════════════════════════════════════ --}}
                    {{-- SKELETON LOADERS (Perceived Performance) --}}
                    {{-- Prohibido usar spinners - Generan ansiedad --}}
                    {{-- Skeletons hacen creer que el contenido ya existe --}}
                    {{-- ══════════════════════════════════════════════════════════ --}}
                    <div 
                        wire:loading 
                        wire:target="loadMore" 
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-4 md:gap-6"
                    >
                        {{-- Skeleton Cards (DRY Loop) --}}
                        @php
                            $skeletonWidths = [
                                ['title' => 'w-3/4', 'meta' => 'w-1/2', 'rating' => 'w-16'],
                                ['title' => 'w-2/3', 'meta' => 'w-2/5', 'rating' => 'w-12'],
                                ['title' => 'w-4/5', 'meta' => 'w-1/3', 'rating' => 'w-14'],
                                ['title' => 'w-3/5', 'meta' => 'w-1/2', 'rating' => 'w-20'],
                            ];
                        @endphp
                        
                        @foreach($skeletonWidths as $index => $widths)
                            <div class="bg-white rounded-2xl overflow-hidden border border-gray-100 animate-pulse
                                {{ $index === 2 ? 'hidden sm:block' : '' }}
                                {{ $index === 3 ? 'hidden 2xl:block' : '' }}">
                                {{-- Imagen skeleton --}}
                                <div class="aspect-[4/3] bg-gray-200"></div>
                                
                                {{-- Contenido skeleton --}}
                                <div class="p-5 space-y-3">
                                    <div class="h-5 bg-gray-200 rounded {{ $widths['title'] }}"></div>
                                    <div class="h-3 bg-gray-100 rounded {{ $widths['meta'] }}"></div>
                                    
                                    {{-- Footer skeleton --}}
                                    <div class="pt-3 border-t border-gray-50 flex justify-between items-center">
                                        <div class="h-4 bg-gray-100 rounded {{ $widths['rating'] }}"></div>
                                        <div class="w-8 h-8 bg-gray-100 rounded-full"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    {{-- Contador (visible cuando no está cargando) --}}
                    <p wire:loading.remove wire:target="loadMore" class="text-center text-sm text-gray-400 py-4">
                        Mostrando {{ $tenants->count() }} de {{ $tenants->total() }} negocios
                    </p>
                </div>
                @else
                {{-- Fin de resultados --}}
                @if($tenants->count() > 0)
                <div class="py-10 text-center">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-500 rounded-full text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Has visto todos los {{ $tenants->total() }} negocios
                    </div>
                </div>
                @endif
                @endif

                @else
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- EMPTY STATE (Growth Hacking) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="py-20 text-center">
                    {{-- Ilustración Empática --}}
                    <div class="w-32 h-32 mx-auto mb-8 text-gray-200">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    
                    {{-- Mensaje Constructivo --}}
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">
                        ¡Vaya! No encontramos resultados
                    </h3>
                    <p class="text-gray-500 mb-2 max-w-md mx-auto">
                        @if($search && $selectedCityName)
                            Aún no tenemos <strong class="text-gray-700">"{{ $search }}"</strong> 
                            en <strong class="text-gray-700">{{ $selectedCityName }}</strong>.
                        @elseif($search)
                            No encontramos negocios que coincidan con 
                            <strong class="text-gray-700">"{{ $search }}"</strong>.
                        @else
                            No hay negocios disponibles con los filtros seleccionados.
                        @endif
                    </p>
                    
                    {{-- Acciones --}}
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mt-8">
                        <button 
                            wire:click="clearFilters"
                            class="inline-flex items-center gap-2 px-6 py-3
                                   bg-gray-900 hover:bg-gray-800 text-white
                                   rounded-full font-medium
                                   transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Limpiar Filtros
                        </button>
                        
                        {{-- CONVERSIÓN VIRAL --}}
                        <a 
                            href="{{ route('register') }}"
                            class="inline-flex items-center gap-2 px-6 py-3
                                   bg-gradient-to-r from-blue-600 to-indigo-600 
                                   hover:from-blue-700 hover:to-indigo-700
                                   text-white rounded-full font-medium
                                   shadow-lg hover:shadow-xl
                                   transition-all"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            ¿Conoces un lugar? Invítalos
                        </a>
                    </div>
                </div>
                @endif
            </main>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- BOTTOM SHEET DRAWER (Mobile Filters) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div 
        x-show="filtersOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
    >
        {{-- Backdrop --}}
        <div 
            x-show="filtersOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="filtersOpen = false"
            class="absolute inset-0 bg-black/60 backdrop-blur-sm"
        ></div>
        
        {{-- Bottom Sheet Panel --}}
        <div 
            x-show="filtersOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 left-0 right-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl overflow-hidden flex flex-col"
        >
            {{-- Handle --}}
            <div class="flex justify-center pt-3 pb-2">
                <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
            </div>
            
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900">Filtros</h2>
                <button 
                    @click="filtersOpen = false"
                    class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            {{-- Contenido de Filtros --}}
            <div class="flex-1 overflow-y-auto p-5 space-y-5">
                @include('livewire.pages.partials.directory-filters')
            </div>
            
            {{-- Footer con Acciones --}}
            <div class="p-4 bg-white border-t border-gray-100 space-y-2">
                <button 
                    wire:click="closeFiltersDrawer"
                    class="w-full py-3.5 bg-gray-900 text-white rounded-xl font-semibold hover:bg-gray-800 transition-colors"
                >
                    Ver {{ number_format($tenants->total()) }} resultados
                </button>
                <button 
                    wire:click="clearFilters"
                    class="w-full py-3 text-gray-500 hover:text-gray-700 font-medium transition-colors"
                >
                    Limpiar todos los filtros
                </button>
            </div>
        </div>
    </div>
</div>

{{-- CSS para ocultar scrollbar --}}
@push('styles')
<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
[x-cloak] { display: none !important; }
</style>
@endpush
