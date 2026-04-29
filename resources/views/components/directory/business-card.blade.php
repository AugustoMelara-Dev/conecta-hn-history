{{--
    ═══════════════════════════════════════════════════════════════════════
    BUSINESS CARD COMPONENT - Micro-Conversion Machine
    ═══════════════════════════════════════════════════════════════════════
    
    Componente atómico optimizado para CTR máximo.
    
    Capas de Ingeniería Visual:
    1. Chasis con física de elevación (hover -translate-y + scale)
    2. Imagen con Cinematic Zoom (group-hover:scale-110)
    3. Fallback premium con textura de categoría
    4. HUD con badges flotantes (PRO + Estado Operativo)
    5. Footer con affordance conectado (group-hover:bg-blue-600)
    
    @param \App\Models\Tenant $tenant
    ═══════════════════════════════════════════════════════════════════════
--}}

@props(['tenant'])

@php
    // Determinar estado operativo
    $isOpen = method_exists($tenant, 'isOpen') ? $tenant->isOpen() : true;
    
    // Color de categoría para fallback
    $categoryColor = $tenant->category->color ?? '#6366f1';
    $categoryIcon = $tenant->category->icon ?? '🏪';
    
    // Tiene imagen?
    $hasImage = $tenant->banner_path || $tenant->logo_path;
    $imageUrl = $tenant->banner_path 
        ? asset('storage/' . $tenant->banner_path) 
        : ($tenant->logo_path ? asset('storage/' . $tenant->logo_path) : null);
@endphp

<a 
    href="{{ route('tenant.show', $tenant->slug) }}"
    class="group relative block h-full bg-white rounded-2xl overflow-hidden isolate
           border border-gray-100 shadow-sm
           transition-all duration-300 ease-out
           hover:shadow-xl hover:-translate-y-1 hover:scale-[1.01] hover:border-gray-200"
>
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- HEMISFERIO SUPERIOR: Experiencia Visual Inmersiva (55%) --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="relative aspect-[4/3] overflow-hidden">
        
        @if($hasImage)
            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- ESCENARIO IDEAL: Imagen con Cinematic Zoom --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <img 
                src="{{ $imageUrl }}" 
                alt="{{ $tenant->name }}"
                class="w-full h-full object-cover 
                       transition-transform duration-700 ease-out
                       group-hover:scale-110"
                loading="lazy"
                decoding="async"
            >
            
            {{-- Capa de Legibilidad (Velvet Rope Gradient) --}}
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
            
        @else
            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- ESCENARIO FALLBACK: Textura Premium de Categoría --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <div 
                class="w-full h-full flex items-center justify-center"
                style="background: linear-gradient(135deg, {{ $categoryColor }}08 0%, {{ $categoryColor }}20 100%);"
            >
                {{-- Icono de categoría gigante como textura --}}
                <span class="text-8xl opacity-10 -rotate-12 select-none">
                    {{ $categoryIcon }}
                </span>
            </div>
            
            {{-- Degradado sutil para consistencia --}}
            <div class="absolute inset-0 bg-gradient-to-t from-gray-100/80 via-transparent to-transparent"></div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- HUD: Sistema de Badges Flotantes (Z-10) --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        
        {{-- BADGE PRO (Esquina Superior Izquierda) --}}
        @if($tenant->is_pro)
        <div class="absolute top-3 left-3 z-10">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 
                         bg-gradient-to-r from-amber-400 to-orange-500
                         text-white text-[10px] font-black uppercase tracking-wide
                         rounded-full shadow-lg shadow-amber-500/30">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                PRO
            </span>
        </div>
        @endif

        {{-- BADGE ESTADO OPERATIVO (Esquina Superior Derecha) --}}
        <div class="absolute top-3 right-3 z-10">
            @if($isOpen)
                {{-- Estado: ABIERTO --}}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 
                             bg-white/90 backdrop-blur-md
                             text-emerald-600 text-[10px] font-bold uppercase tracking-wide
                             rounded-full shadow-lg">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    Abierto
                </span>
            @else
                {{-- Estado: CERRADO --}}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 
                             bg-gray-100 
                             text-gray-500 text-[10px] font-medium uppercase tracking-wide
                             rounded-full">
                    <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                    Cerrado
                </span>
            @endif
        </div>

        {{-- BADGE CATEGORÍA (Esquina Inferior Izquierda) --}}
        @if($tenant->category)
        <div class="absolute bottom-3 left-3 z-10">
            <span class="inline-flex items-center gap-1 px-2.5 py-1
                         bg-white/90 backdrop-blur-md
                         text-gray-700 text-xs font-medium
                         rounded-full shadow-sm">
                {{ $tenant->category->icon ?? '📦' }}
                {{ $tenant->category->name }}
            </span>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- HEMISFERIO INFERIOR: Datos Racionales + CTA (45%) --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="p-5">
        {{-- Título del Negocio --}}
        <h3 class="text-lg font-bold text-gray-900 leading-tight line-clamp-1
                   group-hover:text-blue-600 transition-colors duration-200">
            {{ $tenant->name }}
        </h3>

        {{-- Metadatos --}}
        <p class="mt-1 text-xs font-medium text-gray-500 line-clamp-1">
            @if($tenant->category)
                {{ $tenant->category->name }}
            @endif
            @if($tenant->price_range ?? false)
                <span class="mx-1">•</span>
                {{ $tenant->price_range }}
            @endif
            @if($tenant->city)
                <span class="mx-1">•</span>
                📍 {{ is_object($tenant->city) ? $tenant->city->name : $tenant->city }}
            @endif
        </p>

        {{-- Descripción corta (si existe) --}}
        @if($tenant->description)
        <p class="mt-2 text-sm text-gray-400 line-clamp-2">
            {{ Str::limit($tenant->description, 80) }}
        </p>
        @endif

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- FOOTER DE ACCIÓN --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="mt-4 pt-3 border-t border-gray-50 flex justify-between items-center">
            
            {{-- Izquierda: Prueba Social --}}
            <div class="flex items-center gap-3">
                {{-- Rating --}}
                @if($tenant->rating ?? false)
                <span class="flex items-center gap-1 text-sm">
                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <span class="font-semibold text-gray-700">{{ number_format($tenant->rating, 1) }}</span>
                </span>
                @endif

                {{-- Contador de Productos --}}
                @if($tenant->products_count ?? false)
                <span class="flex items-center gap-1 text-xs text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    {{ $tenant->products_count }}
                </span>
                @endif
            </div>

            {{-- Derecha: Trigger de Acción --}}
            <div class="w-8 h-8 rounded-full bg-gray-50 
                        flex items-center justify-center
                        transition-all duration-300
                        group-hover:bg-blue-600 group-hover:shadow-lg group-hover:shadow-blue-500/30">
                <svg class="w-4 h-4 text-gray-400 transition-colors duration-300
                            group-hover:text-white" 
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </div>
    </div>
</a>
