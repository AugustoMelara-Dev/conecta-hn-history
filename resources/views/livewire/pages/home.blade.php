{{--
    HOME - Página Principal Conecta HN
    
    Arquitectura Mobile-First con máximo SEO y velocidad.
    Stack: Livewire + Alpine.js + Tailwind CSS
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECCIÓN 1: HERO DE BÚSQUEDA INTELIGENTE --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section 
        class="relative min-h-[60vh] lg:min-h-[50vh] flex items-center justify-center overflow-hidden"
        x-data="heroSearch()"
    >
        {{-- Imagen de Fondo con Overlay --}}
        @if($heroImage)
        <div 
            class="absolute inset-0 bg-cover bg-center"
            style="background-image: url('{{ asset('storage/' . $heroImage) }}')"
        ></div>
        @else
        <div class="absolute inset-0 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800"></div>
        @endif
        
        {{-- Overlay Claro para Contraste --}}
        <div class="absolute inset-0 bg-white/90 backdrop-blur-sm"></div>
        
        {{-- Contenido Central --}}
        <div class="relative z-10 w-full max-w-4xl mx-auto px-4 py-12 text-center">
            {{-- Logo y Tagline --}}
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4">
                Conecta<span class="text-blue-600">HN</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-600 mb-8">
                El directorio de negocios más completo de Honduras
            </p>
            
            {{-- Estadísticas Rápidas --}}
            <div class="flex justify-center gap-8 mb-10 text-sm text-gray-500">
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <strong class="text-gray-700">{{ number_format($stats['tenants']) }}</strong> Negocios
                </span>
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <strong class="text-gray-700">{{ $stats['cities'] }}</strong> Ciudades
                </span>
            </div>
            
            {{-- BARRA DE BÚSQUEDA FLOTANTE --}}
            <div class="relative max-w-2xl mx-auto">
                <div class="relative group">
                    {{-- Input de Búsqueda Masivo --}}
                    <input 
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        wire:keydown.enter="performSearch"
                        x-ref="searchInput"
                        @focus="showDropdown = true"
                        @click.away="showDropdown = false"
                        :placeholder="currentPlaceholder"
                        class="w-full h-14 md:h-16 px-6 pr-14 text-lg
                               bg-white border-transparent rounded-full
                               shadow-xl hover:shadow-2xl
                               focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500
                               transition-all duration-300 ease-out
                               placeholder:text-gray-400"
                    >
                    
                    {{-- Botón de Búsqueda --}}
                    <button 
                        wire:click="performSearch"
                        class="absolute right-2 top-1/2 -translate-y-1/2
                               w-10 h-10 md:w-12 md:h-12
                               flex items-center justify-center
                               bg-blue-600 hover:bg-blue-700 
                               rounded-full text-white
                               transition-colors duration-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
                
                {{-- Panel Desplegable de Sugerencias --}}
                <div 
                    x-show="showDropdown"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="absolute top-full left-0 right-0 mt-2
                           bg-white rounded-2xl shadow-2xl border border-gray-100
                           p-4 z-50"
                >
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-3">
                        Categorías en Tendencia
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($categories->take(6) as $category)
                        <a 
                            href="{{ route('category.show', $category->slug) }}"
                            class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 
                                   text-gray-700 rounded-full transition-colors"
                        >
                            {{ $category->name }}
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECCIÓN 2: NAVEGACIÓN TÁCTIL DE CATEGORÍAS (PILLS) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-6 bg-white border-b border-gray-100 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4">
            <div 
                class="flex gap-3 overflow-x-auto snap-x scroll-smooth pb-2
                       scrollbar-hide"
                style="-webkit-overflow-scrolling: touch;"
            >
                @foreach($categories as $category)
                <a 
                    href="{{ route('category.show', $category->slug) }}"
                    class="flex-shrink-0 snap-start
                           flex items-center gap-2 px-5 py-2.5
                           bg-gray-50 hover:bg-gray-900
                           border border-gray-200 hover:border-gray-900
                           text-gray-600 hover:text-white
                           rounded-full whitespace-nowrap
                           transform hover:-translate-y-1
                           transition-all duration-300 ease-out
                           group"
                >
                    {{-- Icono de Categoría --}}
                    @if($category->icon)
                    <span class="text-lg group-hover:scale-110 transition-transform">
                        {{ $category->icon }}
                    </span>
                    @else
                    <span 
                        class="w-2 h-2 rounded-full"
                        style="background-color: {{ $category->color ?? '#3b82f6' }}"
                    ></span>
                    @endif
                    
                    {{-- Nombre de Categoría --}}
                    <span class="font-medium text-sm">{{ $category->name }}</span>
                </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECCIÓN 3: CIUDADES DESTACADAS (SEO LOCAL) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-12 md:py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4">
            {{-- Header de Sección --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                        Explora por Ciudad
                    </h2>
                    <p class="text-gray-500 mt-1">Encuentra negocios cerca de ti</p>
                </div>
                <a 
                    href="{{ route('cities.index') }}"
                    class="hidden md:flex items-center gap-1 text-blue-600 hover:text-blue-700 font-medium"
                >
                    Ver todas
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            
            {{-- Grid de Tarjetas de Ciudades --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($cities as $city)
                <a 
                    href="{{ route('city.show', $city->slug) }}"
                    class="group relative h-64 rounded-2xl overflow-hidden
                           shadow-lg hover:shadow-2xl
                           transform hover:-translate-y-1
                           transition-all duration-300"
                >
                    {{-- Imagen de Fondo --}}
                    @if($city->hero_image)
                    <img 
                        src="{{ asset('storage/' . $city->hero_image) }}" 
                        alt="{{ $city->name }}"
                        class="absolute inset-0 w-full h-full object-cover
                               group-hover:scale-105 transition-transform duration-500"
                        loading="lazy"
                    >
                    @else
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500 to-indigo-600"></div>
                    @endif
                    
                    {{-- Degradado para Legibilidad --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>
                    
                    {{-- Contenido de Tarjeta --}}
                    <div class="absolute inset-0 p-6 flex flex-col justify-end">
                        <h3 class="text-2xl font-bold text-white mb-1">
                            {{ $city->name }}
                        </h3>
                        <p class="text-white/70 text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            {{ number_format($city->tenants_count ?? 0) }} negocios
                        </p>
                        @if($city->department)
                        <span class="mt-2 inline-flex items-center px-2 py-1 text-xs font-medium text-white/80 bg-white/20 rounded-full w-fit">
                            {{ $city->department }}
                        </span>
                        @endif
                    </div>
                    
                    {{-- Hover Indicator --}}
                    <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="flex items-center justify-center w-10 h-10 bg-white rounded-full shadow-lg">
                            <svg class="w-5 h-5 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </span>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECCIÓN 4: NUEVOS EN CONECTA HN (PRUEBA SOCIAL) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-12 md:py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            {{-- Header de Sección --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900">
                        Nuevos en Conecta HN
                    </h2>
                    <p class="text-gray-500 mt-1">Los negocios recién llegados a la plataforma</p>
                </div>
            </div>
            
            {{-- Carrusel Horizontal --}}
            <div 
                class="flex gap-4 overflow-x-auto snap-x scroll-smooth pb-4
                       scrollbar-hide"
                style="-webkit-overflow-scrolling: touch;"
            >
                @foreach($newTenants as $tenant)
                <a 
                    href="{{ route('tenant.show', $tenant->slug) }}"
                    class="flex-shrink-0 snap-start w-64
                           bg-gray-50 hover:bg-white
                           rounded-2xl p-4
                           border border-transparent hover:border-gray-200
                           shadow-sm hover:shadow-lg
                           transform hover:-translate-y-1
                           transition-all duration-300
                           group"
                >
                    {{-- Badge Nuevo --}}
                    <div class="relative mb-4">
                        <span class="absolute -top-1 -right-1 z-10
                                     px-2 py-0.5 text-xs font-bold
                                     bg-green-500 text-white rounded-full
                                     shadow-sm">
                            Nuevo
                        </span>
                        
                        {{-- Logo Circular --}}
                        <div class="w-16 h-16 mx-auto rounded-full overflow-hidden bg-white shadow-md ring-2 ring-gray-100">
                            @if($tenant->logo_path)
                            <img 
                                src="{{ asset('storage/' . $tenant->logo_path) }}" 
                                alt="{{ $tenant->name }}"
                                class="w-full h-full object-cover"
                                loading="lazy"
                            >
                            @else
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-500 to-blue-600 text-white font-bold text-xl">
                                {{ substr($tenant->name, 0, 1) }}
                            </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Info del Negocio --}}
                    <div class="text-center">
                        <h3 class="font-semibold text-gray-900 truncate group-hover:text-blue-600 transition-colors">
                            {{ $tenant->name }}
                        </h3>
                        @if($tenant->category)
                        <span 
                            class="inline-block mt-2 px-2 py-1 text-xs font-medium rounded-full"
                            style="background-color: {{ $tenant->category->color ?? '#e5e7eb' }}20; color: {{ $tenant->category->color ?? '#6b7280' }}"
                        >
                            {{ $tenant->category->name }}
                        </span>
                        @endif
                        <p class="text-xs text-gray-400 mt-2">
                            {{ $tenant->created_at->diffForHumans() }}
                        </p>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- CTA FINAL --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-16 bg-gradient-to-r from-blue-600 to-indigo-700">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
                ¿Tienes un negocio en Honduras?
            </h2>
            <p class="text-blue-100 text-lg mb-8">
                Únete a la plataforma y conecta con miles de clientes potenciales.
            </p>
            <a 
                href="{{ route('register') }}"
                class="inline-flex items-center gap-2 px-8 py-4
                       bg-white text-blue-600 font-bold
                       rounded-full shadow-xl hover:shadow-2xl
                       transform hover:-translate-y-1
                       transition-all duration-300"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Registrar mi negocio gratis
            </a>
        </div>
    </section>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- ALPINE.JS: Typewriter Effect para Search Placeholder --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script>
function heroSearch() {
    return {
        showDropdown: false,
        currentPlaceholder: '',
        placeholderIndex: 0,
        charIndex: 0,
        isDeleting: false,
        placeholders: [
            '¿Buscas Baleadas?',
            '¿Buscas Farmacias?',
            '¿Buscas Repuestos?',
            '¿Buscas Restaurantes?',
            '¿Buscas Hoteles?',
            '¿Buscas Mecánicos?'
        ],
        
        init() {
            this.typeWriter();
        },
        
        typeWriter() {
            const current = this.placeholders[this.placeholderIndex];
            
            if (this.isDeleting) {
                this.currentPlaceholder = current.substring(0, this.charIndex - 1);
                this.charIndex--;
            } else {
                this.currentPlaceholder = current.substring(0, this.charIndex + 1);
                this.charIndex++;
            }
            
            let typeSpeed = this.isDeleting ? 50 : 100;
            
            if (!this.isDeleting && this.charIndex === current.length) {
                typeSpeed = 2000; // Pausa al completar
                this.isDeleting = true;
            } else if (this.isDeleting && this.charIndex === 0) {
                this.isDeleting = false;
                this.placeholderIndex = (this.placeholderIndex + 1) % this.placeholders.length;
                typeSpeed = 500;
            }
            
            setTimeout(() => this.typeWriter(), typeSpeed);
        }
    }
}
</script>
@endpush

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
</style>
@endpush
