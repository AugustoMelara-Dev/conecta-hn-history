{{--
    SHOW-TENANT - Perfil del Negocio (Microsite)
    
    Interfaz Polimórfica:
    - FREE: Horario simple, dirección texto, sin mapa
    - PRO: Horario estructurado con badge Abierto/Cerrado, mapa GPS embebido
    
    Mobile-First | Conversion-Focused | SEO Optimized
--}}

<div class="min-h-screen bg-gray-50 pb-24">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- PREVIEW MODE ALERT (Solo visible para dueño/admin) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($previewMode)
    <div class="fixed top-0 left-0 right-0 z-[60] bg-amber-500 text-amber-900 py-2 px-4 text-center text-sm font-medium shadow-lg">
        <div class="flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
            <span>Vista Previa: {{ $previewReason ?? 'Este negocio no está publicado' }}</span>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HERO SECTION - Portada Inmersiva con Banner Dinámico --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="relative aspect-video max-h-[45vh] min-h-[250px] overflow-hidden @if($previewMode) mt-10 @endif">
        {{-- Banner PRO o Gradiente FREE --}}
        @if($tenant->banner_path)
            {{-- Banner personalizado (PRO) --}}
            <img 
                src="{{ asset('storage/' . $tenant->banner_path) }}" 
                alt="{{ $tenant->name }}"
                class="absolute inset-0 w-full h-full object-cover"
            >
        @else
            {{-- Gradiente elegante (FREE) --}}
            @php
                $primaryColor = $tenant->primary_color ?? '#3b82f6';
            @endphp
            <div 
                class="absolute inset-0"
                style="background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $primaryColor }}99 50%, #1e3a5f 100%)"
            ></div>
            
            {{-- Patrón decorativo --}}
            <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 80 80\"><circle cx=\"40\" cy=\"40\" r=\"2\" fill=\"white\"/></svg>'); background-size: 40px 40px;"></div>
        @endif

        {{-- Overlay Degradado --}}
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>

        {{-- Botón Volver --}}
        <a 
            href="{{ route('directory') }}"
            class="absolute top-4 left-4 z-20 p-2 rounded-full bg-black/30 backdrop-blur-sm text-white hover:bg-black/50 transition-colors"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>

        {{-- Badge PRO --}}
        @if($tenant->is_pro)
        <div class="absolute top-4 right-4 z-20">
            <span class="px-3 py-1 bg-gradient-to-r from-amber-400 to-amber-500 text-black text-xs font-bold rounded-full shadow-lg">
                ⭐ PRO
            </span>
        </div>
        @endif

        {{-- Información sobre la portada --}}
        <div class="absolute bottom-6 left-0 right-0 px-4 z-20">
            <div class="max-w-4xl mx-auto">
                {{-- Categoría --}}
                @if($tenant->category)
                <span 
                    class="inline-block px-3 py-1 text-xs font-medium text-white rounded-full mb-2"
                    style="background-color: {{ $tenant->category->color ?? '#3b82f6' }}"
                >
                    {{ $tenant->category->name }}
                </span>
                @endif

                {{-- Estado Abierto/Cerrado (Solo PRO con datos estructurados) --}}
                @if($hasStructuredHours && $isOpen !== null)
                <div class="flex items-center gap-2 mb-2">
                    @if($isOpen)
                    <span class="flex items-center gap-1.5 px-2.5 py-1 bg-green-500/90 text-white text-xs font-bold rounded-full backdrop-blur-sm">
                        <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                        Abierto Ahora
                    </span>
                    @else
                    <span class="flex items-center gap-1.5 px-2.5 py-1 bg-red-500/90 text-white text-xs font-bold rounded-full backdrop-blur-sm">
                        <span class="w-2 h-2 bg-white rounded-full"></span>
                        Cerrado
                    </span>
                    @endif
                    @if($todayHoursFormatted)
                    <span class="text-white/80 text-xs">{{ $todayHoursFormatted }}</span>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- LOGO FLOTANTE + INFO PRINCIPAL --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="relative max-w-4xl mx-auto px-4">
        <div class="flex items-start gap-4 -mt-12 relative z-30">
            {{-- Logo Circular Flotante --}}
            <div class="flex-shrink-0 w-24 h-24 md:w-28 md:h-28 rounded-full bg-white ring-4 ring-white shadow-xl overflow-hidden">
                @if($tenant->logo_path)
                <img 
                    src="{{ asset('storage/' . $tenant->logo_path) }}" 
                    alt="{{ $tenant->name }}"
                    class="w-full h-full object-cover"
                >
                @else
                <div 
                    class="w-full h-full flex items-center justify-center text-white text-3xl font-bold"
                    style="background: linear-gradient(135deg, {{ $tenant->primary_color ?? '#3b82f6' }}, {{ $tenant->primary_color ?? '#3b82f6' }}dd)"
                >
                    {{ substr($tenant->name, 0, 1) }}
                </div>
                @endif
            </div>

            {{-- Nombre y Ciudad --}}
            <div class="pt-14 md:pt-16 flex-1">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                    {{ $tenant->name }}
                </h1>
                @if($tenant->city)
                <p class="text-gray-500 flex items-center gap-1 mt-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    </svg>
                    {{ $tenant->city->name }}
                </p>
                @endif
            </div>
        </div>

        {{-- Barra de Redes Sociales (Solo si hay) --}}
        @if(count($socialLinks) > 0)
        <div class="flex flex-wrap gap-2 mt-4 ml-28 md:ml-32">
            @foreach($socialLinks as $link)
            <a 
                href="{{ $link['url'] }}" 
                target="_blank" 
                rel="noopener noreferrer"
                class="p-2.5 rounded-full transition-colors {{ $link['color'] }}"
                title="{{ $link['name'] }}"
            >
                @if($link['icon'] === 'facebook')
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.77,7.46H14.5v-1.9c0-.9.6-1.1,1-1.1h3V.5h-4.33C10.24.5,9.5,3.44,9.5,5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4Z"/></svg>
                @elseif($link['icon'] === 'instagram')
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12,2.16c3.2,0,3.58,0,4.85.07,3.25.15,4.77,1.69,4.92,4.92.06,1.27.07,1.65.07,4.85s0,3.59-.07,4.85c-.15,3.23-1.66,4.77-4.92,4.92-1.27.06-1.64.07-4.85.07s-3.58,0-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s0-3.58.07-4.85C2.38,3.92,3.9,2.38,7.15,2.23,8.42,2.18,8.8,2.16,12,2.16ZM12,0C8.74,0,8.33,0,7.05.07c-4.27.2-6.78,2.71-7,7C0,8.33,0,8.74,0,12s0,3.67.07,4.95c.2,4.27,2.71,6.78,7,7C8.33,24,8.74,24,12,24s3.67,0,4.95-.07c4.27-.2,6.78-2.71,7-7C24,15.67,24,15.26,24,12s0-3.67-.07-4.95c-.2-4.27-2.71-6.78-7-7C15.67,0,15.26,0,12,0Zm0,5.84A6.16,6.16,0,1,0,18.16,12,6.16,6.16,0,0,0,12,5.84ZM12,16a4,4,0,1,1,4-4A4,4,0,0,1,12,16ZM18.41,4.15a1.44,1.44,0,1,0,1.44,1.44A1.44,1.44,0,0,0,18.41,4.15Z"/></svg>
                @elseif($link['icon'] === 'tiktok')
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59,6.69a4.83,4.83,0,0,1-3.77-4.25V2h-3.45V15.94a2.91,2.91,0,0,1-5.14,1.9A2.93,2.93,0,0,1,9.52,13.1a2.91,2.91,0,0,1,2.85,2.87v.06a2.89,2.89,0,0,1-.58,1.74v3.26a6.36,6.36,0,1,0,7.34-6.29V11.2A8.16,8.16,0,0,0,24,6.69Z"/></svg>
                @elseif($link['icon'] === 'globe')
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
                @endif
            </a>
            @endforeach
        </div>
        @endif
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- TABS DE NAVEGACIÓN --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section 
        class="sticky top-0 z-40 bg-white border-b border-gray-200 shadow-sm mt-6"
        x-data="{ activeTab: '{{ $activeTab }}' }"
    >
        <div class="max-w-4xl mx-auto px-4">
            <nav class="flex gap-1 overflow-x-auto scrollbar-hide py-2">
                <button 
                    @click="activeTab = 'info'"
                    :class="activeTab === 'info' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium transition-colors"
                >
                    Información
                </button>

                @if($gallery->count() > 0 || $tenant->is_pro)
                <button 
                    @click="activeTab = 'gallery'"
                    :class="activeTab === 'gallery' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium transition-colors"
                >
                    Galería
                </button>
                @endif

                @if($tenant->is_pro && $products->count() > 0)
                <button 
                    @click="activeTab = 'products'"
                    :class="activeTab === 'products' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    class="flex-shrink-0 px-4 py-2 rounded-full text-sm font-medium transition-colors"
                >
                    Productos
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full"
                          :class="activeTab === 'products' ? 'bg-white/20' : 'bg-gray-200'">
                        {{ $products->count() }}
                    </span>
                </button>
                @endif
            </nav>
        </div>
    
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: INFORMACIÓN --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'info'" x-transition class="max-w-4xl mx-auto px-4 py-6 space-y-6">
        
        {{-- Descripción --}}
        @if($tenant->description)
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Acerca de</h2>
            <p class="text-gray-600 leading-relaxed whitespace-pre-line">{{ $tenant->description }}</p>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- MÓDULO DE UBICACIÓN (Polimórfico FREE/PRO) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Ubicación</h2>
            
            @if($hasGpsCoordinates && $mapEmbedUrl)
                {{-- PRO: Mapa embebido con coordenadas GPS --}}
                <div class="rounded-xl overflow-hidden mb-4">
                    <iframe 
                        src="{{ $mapEmbedUrl }}"
                        width="100%"
                        height="250"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        class="w-full"
                    ></iframe>
                </div>
                
                {{-- Botones de navegación --}}
                <div class="flex gap-3">
                    <a 
                        href="{{ $this->getMapsUrl() }}"
                        target="_blank"
                        class="flex-1 flex items-center justify-center gap-2 py-3 bg-blue-50 text-blue-700 font-medium rounded-xl hover:bg-blue-100 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                        Google Maps
                    </a>
                    <a 
                        href="{{ $this->getWazeUrl() }}"
                        target="_blank"
                        class="flex-1 flex items-center justify-center gap-2 py-3 bg-cyan-50 text-cyan-700 font-medium rounded-xl hover:bg-cyan-100 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.1,2C6.54,2,2,6.54,2,12.1c0,5.56,4.54,10.1,10.1,10.1S22.2,17.66,22.2,12.1C22.2,6.54,17.66,2,12.1,2z M9.9,16.3c-0.83,0-1.5-0.67-1.5-1.5s0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5S10.73,16.3,9.9,16.3z M14.3,16.3c-0.83,0-1.5-0.67-1.5-1.5s0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5S15.13,16.3,14.3,16.3z M17.1,11.2c0,0.55-0.45,1-1,1H7.9c-0.55,0-1-0.45-1-1V9.1c0-2.76,2.24-5,5-5h0.2c2.76,0,5,2.24,5,5V11.2z"/>
                        </svg>
                        Waze
                    </a>
                </div>
                
                {{-- Dirección de texto debajo del mapa --}}
                @if($tenant->address)
                <p class="text-gray-500 text-sm mt-4 flex items-start gap-2">
                    <svg class="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    </svg>
                    {{ $tenant->address }}
                </p>
                @endif
            @else
                {{-- FREE: Solo dirección de texto --}}
                @if($tenant->address)
                <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-100 text-blue-600 rounded-full flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-gray-700">{{ $tenant->address }}</p>
                        <a 
                            href="{{ $this->getMapsUrl() }}"
                            target="_blank"
                            class="text-blue-600 text-sm font-medium mt-2 inline-flex items-center gap-1 hover:underline"
                        >
                            Ver en mapa
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                @else
                <p class="text-gray-400 text-center py-4">Dirección no especificada</p>
                @endif
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- MÓDULO DE HORARIOS (Polimórfico FREE/PRO) --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Horario de Atención</h2>
                
                {{-- Badge Abierto/Cerrado (Solo PRO) --}}
                @if($hasStructuredHours && $isOpen !== null)
                    @if($isOpen)
                    <span class="flex items-center gap-1.5 px-3 py-1 bg-green-100 text-green-700 text-sm font-bold rounded-full">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        Abierto
                    </span>
                    @else
                    <span class="flex items-center gap-1.5 px-3 py-1 bg-red-100 text-red-700 text-sm font-bold rounded-full">
                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                        Cerrado
                    </span>
                    @endif
                @endif
            </div>

            @if($scheduleDisplay['type'] === 'structured')
                {{-- PRO: Tabla de horarios estructurados --}}
                <div class="space-y-2">
                    @foreach($scheduleDisplay['data'] as $dayInfo)
                    <div class="flex justify-between items-center py-2.5 px-3 rounded-lg {{ $dayInfo['isToday'] ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
                        <span class="font-medium {{ $dayInfo['isToday'] ? 'text-blue-700' : 'text-gray-700' }}">
                            {{ $dayInfo['day'] }}
                            @if($dayInfo['isToday'])
                            <span class="text-xs text-blue-500 ml-1">(Hoy)</span>
                            @endif
                        </span>
                        <span class="{{ $dayInfo['isClosed'] ? 'text-red-500 font-medium' : 'text-gray-600' }}">
                            {{ $dayInfo['hours'] }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @else
                {{-- FREE: Texto simple --}}
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl">
                    <div class="w-10 h-10 flex items-center justify-center bg-blue-100 text-blue-600 rounded-full flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-gray-700">{{ $scheduleDisplay['data'] }}</p>
                </div>
            @endif
        </div>

        {{-- Características/Features --}}
        @if(count($features) > 0)
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Características</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($features as $feature)
                <span class="px-3 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-full">
                    {{ $feature }}
                </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: GALERÍA --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'gallery'" x-transition class="max-w-4xl mx-auto px-4 py-6">
        @if($gallery->count() > 0)
        <div class="grid grid-cols-3 gap-2">
            @foreach($gallery as $image)
            <div class="aspect-square rounded-lg overflow-hidden bg-gray-100">
                <img 
                    src="{{ asset('storage/' . $image->path) }}" 
                    alt="{{ $image->alt ?? $tenant->name }}"
                    class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                    loading="lazy"
                >
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <p class="text-gray-500">No hay fotos disponibles</p>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: PRODUCTOS (Solo PRO) --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($tenant->is_pro)
    <div x-show="activeTab === 'products'" x-transition class="max-w-4xl mx-auto px-4 py-6">
        
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- BARRA DE NAVEGACIÓN POR SECCIONES (Sticky Glass Navigation) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- Arquitectura CSS:                                                --}}
        {{-- - sticky top-12: Anclaje bajo header principal                   --}}
        {{-- - z-40: Por encima del contenido, debajo de modales              --}}
        {{-- - backdrop-blur-md: Efecto cristal premium                       --}}
        {{-- - shadow-md: Separación visual del contenido scrolleable         --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if($menuSections && count($menuSections) > 0)
        <div 
            id="section-nav"
            class="sticky top-0 z-40 -mx-4 px-4 py-3 bg-white/90 backdrop-blur-md shadow-md border-b border-gray-100/50"
            x-data="{ 
                scrollToProducts() {
                    setTimeout(() => {
                        document.getElementById('products-grid')?.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }, 100);
                }
            }"
        >
            <div class="flex gap-2 overflow-x-auto snap-x snap-mandatory scrollbar-hide pb-1 -mb-1">
                {{-- ═══ Botón "Ver Todo" (Reset Filter) ═══ --}}
                <button 
                    wire:click="setActiveSection(null)"
                    x-on:click="scrollToProducts()"
                    class="flex-shrink-0 px-4 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 snap-start whitespace-nowrap
                        {{ $activeSectionId === null 
                            ? 'text-white shadow-lg scale-105' 
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:scale-102' }}"
                    @if($activeSectionId === null)
                    style="background: linear-gradient(135deg, {{ $tenant->primary_color ?? '#3b82f6' }}, {{ $tenant->primary_color ?? '#3b82f6' }}dd)"
                    @endif
                >
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        Todos
                    </span>
                </button>

                {{-- ═══ Pestañas de Secciones Dinámicas ═══ --}}
                @foreach($menuSections as $section)
                <button 
                    wire:click="setActiveSection({{ $section->id }})"
                    x-on:click="scrollToProducts()"
                    wire:loading.class="opacity-60 cursor-wait"
                    wire:target="setActiveSection"
                    class="flex-shrink-0 px-4 py-2.5 rounded-full text-sm font-semibold transition-all duration-200 snap-start whitespace-nowrap
                        {{ $activeSectionId === $section->id 
                            ? 'text-white shadow-lg scale-105' 
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:scale-102' }}"
                    @if($activeSectionId === $section->id)
                    style="background: linear-gradient(135deg, {{ $tenant->primary_color ?? '#3b82f6' }}, {{ $tenant->primary_color ?? '#3b82f6' }}dd)"
                    @endif
                >
                    {{ $section->name }}
                    @if($section->products_count > 0)
                    <span class="ml-1.5 px-2 py-0.5 text-xs font-bold rounded-full 
                        {{ $activeSectionId === $section->id ? 'bg-white/25' : 'bg-gray-200 text-gray-700' }}">
                        {{ $section->products_count }}
                    </span>
                    @endif
                </button>
                @endforeach
            </div>
            
            {{-- Indicador de scroll horizontal (gradiente fade) --}}
            <div class="absolute right-0 top-0 bottom-0 w-8 bg-gradient-to-l from-white/90 to-transparent pointer-events-none"></div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- GRID DE PRODUCTOS (Con loading state) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if($products->count() > 0)
        <div 
            id="products-grid"
            class="grid grid-cols-2 md:grid-cols-3 gap-4 transition-all duration-300"
            wire:loading.class="opacity-50 grayscale pointer-events-none"
            wire:target="setActiveSection"
        >
            @foreach($products as $product)
            <div 
                wire:click="openProductModal({{ $product->id }})"
                class="bg-white rounded-2xl shadow-sm overflow-hidden cursor-pointer hover:shadow-lg transition-shadow group"
            >
                <div class="aspect-square bg-gray-100 overflow-hidden">
                    @if($product->image_path)
                    <img 
                        src="{{ asset('storage/' . $product->image_path) }}" 
                        alt="{{ $product->name }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                    >
                    @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    @endif
                </div>
                <div class="p-3">
                    <h3 class="font-medium text-gray-900 truncate">{{ $product->name }}</h3>
                    @if($product->price)
                    <p class="font-bold mt-1" style="color: {{ $tenant->primary_color ?? '#3b82f6' }}">
                        L. {{ number_format($product->price, 2) }}
                    </p>
                    @endif
                    {{-- Badge de sección (cuando se ve "Todos") --}}
                    @if($activeSectionId === null && $product->menuSection)
                    <span class="inline-block mt-1 px-2 py-0.5 bg-gray-100 text-gray-500 text-xs rounded-full truncate max-w-full">
                        {{ $product->menuSection->name }}
                    </span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
            </svg>
            <p class="text-gray-500">
                @if($activeSectionId)
                    No hay productos en esta sección
                @else
                    No hay productos disponibles
                @endif
            </p>
            @if($activeSectionId)
            <button 
                wire:click="setActiveSection(null)"
                class="mt-4 text-sm font-medium hover:underline"
                style="color: {{ $tenant->primary_color ?? '#3b82f6' }}"
            >
                Ver todos los productos
            </button>
            @endif
        </div>
        @endif
    </div>
    @endif
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- MODAL DE PRODUCTO --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    @if($showProductModal && $selectedProduct)
    <div 
        class="fixed inset-0 z-50 overflow-y-auto"
        x-data
        x-init="document.body.style.overflow = 'hidden'"
        x-on:click.away="$wire.closeProductModal()"
    >
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeProductModal"></div>
        
        <div class="relative min-h-screen flex items-end md:items-center justify-center p-0 md:p-4">
            <div class="bg-white w-full md:max-w-lg md:rounded-2xl overflow-hidden rounded-t-3xl">
                {{-- Imagen --}}
                @if($selectedProduct->image_path)
                <div class="aspect-square bg-gray-100">
                    <img 
                        src="{{ asset('storage/' . $selectedProduct->image_path) }}" 
                        alt="{{ $selectedProduct->name }}"
                        class="w-full h-full object-cover"
                    >
                </div>
                @endif

                {{-- Contenido --}}
                <div class="p-5">
                    <h3 class="text-xl font-bold text-gray-900">{{ $selectedProduct->name }}</h3>
                    
                    @if($selectedProduct->price)
                    <p class="text-2xl font-bold text-blue-600 mt-2">L. {{ number_format($selectedProduct->price, 2) }}</p>
                    @endif

                    @if($selectedProduct->description)
                    <p class="text-gray-600 mt-3">{{ $selectedProduct->description }}</p>
                    @endif

                    {{-- Botón WhatsApp para este producto --}}
                    <a 
                        href="{{ $this->getWhatsAppUrl($selectedProduct->name) }}"
                        target="_blank"
                        class="flex items-center justify-center gap-2 w-full mt-5 py-4 bg-[#25D366] hover:bg-[#20BD5A] text-white font-bold rounded-xl transition-colors"
                    >
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                        </svg>
                        Pedir por WhatsApp
                    </a>

                    {{-- Botón Cerrar --}}
                    <button 
                        wire:click="closeProductModal"
                        class="w-full mt-3 py-3 text-gray-500 hover:text-gray-700 font-medium transition-colors"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- STICKY ACTION BAR (Móvil) - WhatsApp Prominente --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-lg safe-area-inset-bottom">
        <div class="max-w-4xl mx-auto px-4 py-3">
            <div class="flex items-center gap-3">
                {{-- WhatsApp (Primario) --}}
                @if($tenant->whatsapp_number || $tenant->phone)
                <a 
                    href="{{ $this->getWhatsAppUrl() }}"
                    target="_blank"
                    class="flex-1 flex items-center justify-center gap-2 py-3.5 bg-[#25D366] hover:bg-[#20BD5A] text-white font-bold rounded-xl transition-colors shadow-lg"
                >
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    <span class="hidden sm:inline">Contactar</span>
                </a>
                @endif

                {{-- Llamar --}}
                @if($tenant->phone)
                <a 
                    href="{{ $this->getPhoneUrl() }}"
                    class="flex items-center justify-center w-14 h-14 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors"
                    title="Llamar"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                </a>
                @endif

                {{-- Maps/Navegación --}}
                @if($tenant->address || $hasGpsCoordinates)
                <a 
                    href="{{ $this->getMapsUrl() }}"
                    target="_blank"
                    class="flex items-center justify-center w-14 h-14 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition-colors"
                    title="Cómo llegar"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- CSS para safe area en iOS --}}
@push('styles')
<style>
.safe-area-inset-bottom {
    padding-bottom: env(safe-area-inset-bottom, 0);
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
@endpush
