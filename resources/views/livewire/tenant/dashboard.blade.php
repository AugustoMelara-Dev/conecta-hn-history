{{--
    Tenant Dashboard - Conditional Behavior Interface
    
    Behavior:
    - PRO users: Full dashboard, all features enabled
    - FREE users: Persistent conversion banner + resource limits + controlled friction
--}}

<div class="min-h-screen bg-gray-50">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                {{-- Logo + Tenant Name --}}
                <div class="flex items-center gap-4">
                    <a href="/" wire:navigate class="text-xl font-bold text-gray-900">
                        Conecta<span class="text-blue-600">HN</span>
                    </a>
                    <span class="text-gray-300">|</span>
                    <span class="font-semibold text-gray-700 truncate max-w-[200px]">
                        {{ $tenant->name }}
                    </span>
                    @if($tenant->is_pro)
                        <span class="px-2 py-0.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-xs font-bold rounded-full">
                            PRO
                        </span>
                    @endif
                </div>

                {{-- Quick Actions --}}
                <div class="flex items-center gap-3">
                    <a 
                        href="/{{ $tenant->slug }}" 
                        target="_blank"
                        class="text-sm text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-1"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Ver Micrositio
                    </a>
                    <div class="h-6 w-px bg-gray-200"></div>
                    <a 
                        href="/admin" 
                        wire:navigate
                        class="text-sm text-gray-600 hover:text-gray-900 transition-colors"
                    >
                        Panel Completo →
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- BANNER DE CONVERSIÓN (Solo FREE) - FRICCIÓN INTENCIONAL --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        @if($this->isFree)
            <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl p-4 md:p-5">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">⚠️</span>
                        <div>
                            <h3 class="font-bold text-amber-900">
                                Estás en el Plan Gratuito
                            </h3>
                            <p class="text-amber-800 text-sm mt-1">
                                Tus clientes <strong>NO pueden</strong> enviarte pedidos por WhatsApp 
                                y tu visibilidad está <strong>limitada</strong> en el directorio.
                            </p>
                        </div>
                    </div>
                    <a 
                        href="/upgrade/{{ $tenant->slug }}" 
                        wire:navigate
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl transition-all shadow-lg shadow-amber-500/25 whitespace-nowrap"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Activar Ventas PRO
                    </a>
                </div>
            </div>
        @endif

        {{-- Welcome Message --}}
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                ¡Hola, {{ auth()->user()->name }}! 👋
            </h1>
            <p class="text-gray-600 mt-1">
                Aquí está el resumen de tu negocio
            </p>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- STATS GRID --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            {{-- ─────────────────────────────────────────────────────────────────── --}}
            {{-- COMPONENTE B: MEDIDOR "TECHO DE CRISTAL" (Límite de Productos) --}}
            {{-- ─────────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Productos</h3>
                    <span class="text-2xl">📦</span>
                </div>

                {{-- Stats --}}
                <div class="flex items-baseline gap-1 mb-4">
                    <span class="text-3xl font-bold text-gray-900">{{ $this->stats['product_count'] }}</span>
                    <span class="text-gray-500">/ {{ $this->stats['product_limit'] }}</span>
                </div>

                {{-- Progress Bar --}}
                <div class="mb-3">
                    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div 
                            class="h-full rounded-full transition-all duration-500 {{ $this->stats['is_danger_zone'] ? 'bg-red-500 animate-pulse' : 'bg-blue-600' }}"
                            style="width: {{ $this->stats['product_percentage'] }}%"
                        ></div>
                    </div>
                </div>

                {{-- Status Text --}}
                <p class="text-sm {{ $this->stats['is_danger_zone'] ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                    @if($this->stats['is_at_limit'])
                        ¡Límite alcanzado! Mejora tu plan para añadir más.
                    @elseif($this->stats['is_danger_zone'])
                        ⚠️ Cerca del límite. Solo quedan {{ $this->stats['products_remaining'] }} espacios.
                    @else
                        Has usado {{ $this->stats['product_count'] }} de {{ $this->stats['product_limit'] }} productos.
                    @endif
                </p>

                {{-- Action Button --}}
                <div class="mt-4 pt-4 border-t border-gray-100">
                    @if($this->stats['is_at_limit'] && $this->isFree)
                        <a 
                            href="/upgrade/{{ $tenant->slug }}" 
                            wire:navigate
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 text-white font-semibold rounded-xl transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                            </svg>
                            Activar Ilimitado
                        </a>
                    @else
                        <a 
                            href="/admin/products/create" 
                            wire:navigate
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-all {{ $this->stats['is_at_limit'] ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Añadir Producto
                        </a>
                    @endif
                </div>
            </div>

            {{-- ─────────────────────────────────────────────────────────────────── --}}
            {{-- COMPONENTE C: VALIDACIÓN SOCIAL (Visitas) --}}
            {{-- ─────────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Visitas</h3>
                    <span class="text-2xl">👀</span>
                </div>

                {{-- Total Visits --}}
                <div class="flex items-baseline gap-1 mb-2">
                    <span class="text-3xl font-bold text-gray-900">{{ number_format($this->stats['total_visits']) }}</span>
                    <span class="text-gray-500">totales</span>
                </div>

                {{-- Period Stats --}}
                <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-100">
                    <div>
                        <p class="text-2xl font-bold text-blue-600">{{ $this->stats['visits_today'] }}</p>
                        <p class="text-sm text-gray-500">Hoy</p>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-indigo-600">{{ $this->stats['visits_this_week'] }}</p>
                        <p class="text-sm text-gray-500">Esta semana</p>
                    </div>
                </div>

                {{-- Social Proof for FREE users --}}
                @if($this->isFree && $this->stats['total_visits'] > 10)
                    <div class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-100">
                        <p class="text-sm text-amber-800">
                            💡 <strong>{{ $this->stats['total_visits'] }} personas</strong> han visto tu negocio. 
                            Con PRO, podrían contactarte directamente.
                        </p>
                    </div>
                @endif
            </div>

            {{-- ─────────────────────────────────────────────────────────────────── --}}
            {{-- INTERACCIONES (WhatsApp & Favoritos) --}}
            {{-- ─────────────────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-900">Interacciones</h3>
                    <span class="text-2xl">💬</span>
                </div>

                <div class="space-y-4">
                    {{-- WhatsApp Clicks --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">WhatsApp</p>
                                <p class="text-sm text-gray-500">Clics al botón</p>
                            </div>
                        </div>
                        <span class="text-2xl font-bold {{ $tenant->is_pro ? 'text-green-600' : 'text-gray-400' }}">
                            {{ $this->stats['whatsapp_clicks'] }}
                        </span>
                    </div>

                    {{-- Favorites --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">Favoritos</p>
                                <p class="text-sm text-gray-500">Usuarios que te siguen</p>
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-red-500">
                            {{ $this->stats['favorites_count'] }}
                        </span>
                    </div>
                </div>

                {{-- PRO Teaser --}}
                @if($this->isFree)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-sm text-gray-500 text-center">
                            🔒 Estadísticas avanzadas disponibles en 
                            <a href="/upgrade/{{ $tenant->slug }}" wire:navigate class="text-indigo-600 font-medium hover:underline">Plan PRO</a>
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        {{-- QUICK ACTIONS (PRO Feature Teaser for FREE users) --}}
        {{-- ═══════════════════════════════════════════════════════════════════════ --}}
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Edit Profile --}}
            <a 
                href="/admin/profile" 
                wire:navigate
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-gray-200 transition-all group"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">
                            Editar Perfil
                        </h3>
                        <p class="text-sm text-gray-500">
                            Actualiza tu información y horarios
                        </p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 ml-auto group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            {{-- Manage Products --}}
            <a 
                href="/admin/products" 
                wire:navigate
                class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-gray-200 transition-all group"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 group-hover:text-emerald-600 transition-colors">
                            Gestionar Productos
                        </h3>
                        <p class="text-sm text-gray-500">
                            Añade, edita o elimina productos
                        </p>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 ml-auto group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        </div>
    </main>
</div>
