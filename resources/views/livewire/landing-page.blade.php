{{--
    LANDING PAGE - Pagina de ventas B2B para Dovra AI
    
    Objetivo: Convencer a dueños de negocios de registrarse
    Transmite: Confianza, modernidad, "cero fricción"
    
    NO es para buscar comida, es una página de ventas SaaS.
--}}

<div class="min-h-screen bg-gradient-to-br from-indigo-50/50 via-white to-white grid-pattern font-sans">
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- NAVBAR - Glassmorphism + Alpine.js State --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <header 
        x-data="{ mobileMenuOpen: false, scrolled: false }"
        @scroll.window="scrolled = (window.pageYOffset > 20)"
        :class="scrolled ? 'bg-white/90 shadow-sm' : 'bg-white/80'"
        class="fixed top-0 left-0 right-0 z-50 backdrop-blur-md border-b border-slate-200/50 transition-all duration-300"
    >
        <nav class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- ZONA IZQUIERDA: BRANDING --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <a href="/" class="flex items-center gap-2.5 group">
                    {{-- Isotipo con gradiente --}}
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-600 to-violet-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:shadow-indigo-500/40 transition-shadow">
                        <span class="text-white font-bold text-lg">V</span>
                    </div>
                    {{-- Logotipo --}}
                    <span class="font-extrabold tracking-tight text-xl text-slate-900">
                        Conecta<span class="text-indigo-600">HN</span>
                    </span>
                </a>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- ZONA CENTRAL: NAVEGACIÓN DESKTOP (Anchor Links) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="hidden md:flex items-center gap-8">
                    <a 
                        href="#features" 
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors relative group"
                    >
                        Beneficios
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a 
                        href="#pricing" 
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors relative group"
                    >
                        Precios
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a 
                        href="#faq" 
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors relative group"
                    >
                        FAQ
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                    <a 
                        href="{{ route('directory.index') }}"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors relative group"
                    >
                        Directorio
                        <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 group-hover:w-full transition-all duration-300"></span>
                    </a>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- ZONA DERECHA: THE MONEY ZONE (CTAs) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="hidden md:flex items-center gap-4">
                    {{-- Login Link (Ghost) --}}
                    <a 
                        href="/login" 
                        class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition-colors"
                    >
                        Iniciar Sesión
                    </a>
                    
                    {{-- Register Button (CTA Dominante) --}}
                    <a 
                        href="/registrar-negocio"
                        class="px-6 py-2.5 rounded-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold shadow-lg shadow-slate-900/20 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200"
                    >
                        Registrar Negocio
                    </a>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- BOTÓN HAMBURGUESA (Mobile Only) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <button 
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden p-2 rounded-lg hover:bg-slate-100 transition-colors"
                    :class="mobileMenuOpen ? 'bg-slate-100' : ''"
                    aria-label="Toggle menu"
                >
                    {{-- Icono Hamburguesa / X --}}
                    <svg 
                        x-show="!mobileMenuOpen" 
                        class="w-6 h-6 text-slate-700" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <svg 
                        x-show="mobileMenuOpen" 
                        x-cloak
                        class="w-6 h-6 text-slate-700" 
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- MENÚ MÓVIL DESPLEGABLE --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div 
                x-show="mobileMenuOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="md:hidden mt-4 pb-4 border-t border-slate-100 pt-4"
            >
                {{-- Links de Navegación --}}
                <div class="space-y-1 mb-4">
                    <a 
                        href="#features" 
                        @click="mobileMenuOpen = false"
                        class="block px-4 py-3 rounded-lg text-slate-700 hover:bg-slate-50 hover:text-indigo-600 font-medium transition-colors"
                    >
                        Beneficios
                    </a>
                    <a 
                        href="#pricing" 
                        @click="mobileMenuOpen = false"
                        class="block px-4 py-3 rounded-lg text-slate-700 hover:bg-slate-50 hover:text-indigo-600 font-medium transition-colors"
                    >
                        Precios
                    </a>
                    <a 
                        href="#faq" 
                        @click="mobileMenuOpen = false"
                        class="block px-4 py-3 rounded-lg text-slate-700 hover:bg-slate-50 hover:text-indigo-600 font-medium transition-colors"
                    >
                        FAQ
                    </a>
                    <a 
                        href="{{ route('directory.index') }}"
                        @click="mobileMenuOpen = false"
                        class="block px-4 py-3 rounded-lg text-slate-700 hover:bg-slate-50 hover:text-indigo-600 font-medium transition-colors"
                    >
                        Directorio
                    </a>
                </div>

                {{-- Divider --}}
                <div class="border-t border-slate-100 my-4"></div>

                {{-- Botones de Acción (Full Width) --}}
                <div class="space-y-3">
                    <a 
                        href="/login"
                        class="block w-full px-4 py-3 text-center rounded-xl border-2 border-slate-200 text-slate-700 font-semibold hover:border-indigo-600 hover:text-indigo-600 transition-colors"
                    >
                        Iniciar Sesión
                    </a>
                    <a 
                        href="/registrar-negocio"
                        class="block w-full px-4 py-3 text-center rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-500 transition-colors shadow-lg shadow-indigo-500/20"
                    >
                        Registrar Mi Negocio
                    </a>
                </div>
            </div>
        </nav>
    </header>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- HERO SECTION - High Impact B2B Sales --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <main class="min-h-screen relative overflow-hidden">
        {{-- Decorative Elements --}}
        <div class="absolute top-20 right-0 w-96 h-96 bg-indigo-100 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-violet-100 rounded-full blur-3xl opacity-40"></div>
        
        <div class="relative max-w-7xl mx-auto px-6 sm:px-8 pt-32 pb-16 lg:pt-40 lg:pb-24">
            <div class="grid lg:grid-cols-12 gap-12 items-center">
                
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- COLUMNA IZQUIERDA: COPYWRITING DE ALTO IMPACTO --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="lg:col-span-7 text-center lg:text-left flex flex-col justify-center">
                    {{-- Badge de Noticia --}}
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-indigo-200 bg-indigo-50/80 backdrop-blur-sm text-indigo-700 text-xs font-bold uppercase tracking-wider mb-8 mx-auto lg:mx-0 w-fit">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                        La plataforma #1 para negocios en Honduras
                    </div>

                    {{-- H1 - Título Principal (Jerarquía Tipográfica Extrema) --}}
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-extrabold text-slate-900 tracking-tight leading-[1.08]">
                        Tu Negocio Merece su Propia 
                        <span class="relative">
                            <span class="relative z-10">Sede Digital.</span>
                            <svg class="absolute -bottom-2 left-0 w-full h-3 text-indigo-200" viewBox="0 0 200 12" preserveAspectRatio="none">
                                <path d="M0,8 Q50,0 100,8 T200,8" stroke="currentColor" stroke-width="4" fill="none"/>
                            </svg>
                        </span>
                        <br class="hidden md:block">
                        <span class="block mt-2">
                            Crea tu Sitio en 
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-violet-600">5 Minutos</span>.
                        </span>
                    </h1>

                    {{-- Subtítulo - La Tesis --}}
                    <p class="mt-8 text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                        Deja de depender del caos de Facebook. Ten tu propio catálogo, recibe pedidos automáticos por WhatsApp y profesionaliza tu marca hoy mismo 
                        <span class="font-semibold text-slate-700">sin saber programar</span>.
                    </p>

                    {{-- ZONA DE ACCIÓN: CTAs --}}
                    <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                        {{-- CTA Primario --}}
                        <a 
                            href="{{ route('tenant.register') }}"
                            class="group inline-flex items-center justify-center gap-3 h-14 sm:h-16 px-8 sm:px-10 rounded-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-lg shadow-xl shadow-indigo-500/30 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-indigo-500/40"
                        >
                            <span>🚀</span>
                            <span>Crear mi Tienda GRATIS</span>
                            <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        
                        {{-- CTA Secundario --}}
                        <a 
                            href="{{ route('directory.index') }}"
                            class="group inline-flex items-center justify-center gap-3 h-14 sm:h-16 px-8 rounded-full bg-white border-2 border-slate-200 hover:border-indigo-300 text-slate-700 hover:text-indigo-600 font-semibold text-lg transition-all duration-300 hover:bg-indigo-50/50"
                        >
                            <svg class="w-6 h-6 text-indigo-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <span>Ver Demo en Vivo</span>
                        </a>
                    </div>

                    {{-- Elemento de Confianza --}}
                    <div class="mt-8 flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-3 text-sm text-slate-500">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 00-1.032 0 11.209 11.209 0 01-7.877 3.08.75.75 0 00-.722.515A12.74 12.74 0 002.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 00.374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 00-.722-.516l-.143.001c-2.996 0-5.717-1.17-7.734-3.08zm3.094 8.016a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" />
                            </svg>
                            No requiere tarjeta de crédito
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zm0 8.625a1.125 1.125 0 100 2.25 1.125 1.125 0 000-2.25zM15.375 12a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0zM7.5 10.875a1.125 1.125 0 100 2.25 1.125 1.125 0 000-2.25z" clip-rule="evenodd" />
                            </svg>
                            Plan Gratuito de por vida
                        </div>
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- COLUMNA DERECHA: iPHONE 15 PRO MOCKUP (CSS PURO) --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div class="lg:col-span-5 flex justify-center lg:justify-end">
                    <div class="relative" style="perspective: 2000px;">
                        {{-- Glow Effect --}}
                        <div class="absolute -inset-4 bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 rounded-[4rem] blur-2xl opacity-20 animate-pulse"></div>
                        
                        {{-- Sombra Difusa Inferior --}}
                        <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 w-56 h-8 bg-slate-900/30 rounded-full blur-2xl"></div>
                        
                        {{-- iPhone 15 Pro Mockup con Animación Flotante --}}
                        <div 
                            class="relative animate-float"
                            style="transform: rotateY(-12deg) rotateX(5deg) rotateZ(2deg);"
                        >
                            {{-- Chasis del Dispositivo (Titanium Frame) --}}
                            <div 
                                class="relative border-[14px] border-slate-800 rounded-[3.5rem] bg-black overflow-hidden shadow-2xl"
                                style="width: 300px; box-shadow: inset 0 0 20px rgba(0,0,0,0.3), 0 50px 100px -20px rgba(0,0,0,0.5);"
                            >
                                {{-- Botones Físicos (Izquierda) --}}
                                <div class="absolute -left-[18px] top-24 w-1 h-8 bg-slate-700 rounded-l-sm"></div>
                                <div class="absolute -left-[18px] top-36 w-1 h-12 bg-slate-700 rounded-l-sm"></div>
                                <div class="absolute -left-[18px] top-52 w-1 h-12 bg-slate-700 rounded-l-sm"></div>
                                
                                {{-- Botón Power (Derecha) --}}
                                <div class="absolute -right-[18px] top-32 w-1 h-16 bg-slate-700 rounded-r-sm"></div>
                                
                                {{-- Dynamic Island --}}
                                <div class="absolute top-4 left-1/2 -translate-x-1/2 w-28 h-8 bg-black rounded-full z-20 flex items-center justify-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-slate-800 ring-1 ring-slate-700"></div>
                                    <div class="w-2 h-2 rounded-full bg-slate-800"></div>
                                </div>
                                
                                {{-- Pantalla del Dispositivo --}}
                                <div class="aspect-[9/19.5] bg-white relative overflow-hidden rounded-[2.5rem]">
                                    {{-- Status Bar --}}
                                    <div class="absolute top-0 left-0 right-0 h-12 bg-white/90 backdrop-blur-sm z-10 flex items-end justify-between px-8 pb-1">
                                        <span class="text-xs font-semibold text-slate-900">9:41</span>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-slate-900" fill="currentColor" viewBox="0 0 24 24"><path d="M12 18c3.31 0 6-2.69 6-6s-2.69-6-6-6-6 2.69-6 6 2.69 6 6 6zm0-10c2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4 1.79-4 4-4z"/></svg>
                                            <svg class="w-4 h-4 text-slate-900" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3C6.95 3 3 6.95 3 12c0 1.85.56 3.57 1.5 5L3 21l4-1.5c1.43.94 3.15 1.5 5 1.5 5.05 0 9-3.95 9-9s-3.95-9-9-9zm0 16c-1.57 0-3.05-.47-4.28-1.28l-.3-.18-3.12 1.13 1.13-3.12-.18-.3A6.948 6.948 0 014 12c0-4.41 3.59-8 8-8s8 3.59 8 8-3.59 8-8 8z"/></svg>
                                            <div class="w-6 h-3 bg-slate-900 rounded-sm relative">
                                                <div class="absolute right-0 top-1/2 -translate-y-1/2 -right-0.5 w-0.5 h-1.5 bg-slate-900 rounded-r-sm"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- App Header --}}
                                    <div class="mt-12 bg-white border-b border-gray-100 px-4 py-3 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-xl flex items-center justify-center shadow-lg">
                                                <span class="text-white text-sm font-bold">V</span>
                                            </div>
                                            <div>
                                                <span class="text-sm font-bold text-slate-900 block leading-tight">Pizzería Italia</span>
                                                <span class="text-[10px] text-slate-500">Tegucigalpa</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 px-2 py-1 bg-green-50 rounded-full">
                                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                                            <span class="text-[10px] text-green-700 font-semibold">Abierto</span>
                                        </div>
                                    </div>

                                    {{-- Banner de Comida --}}
                                    <div class="relative h-32 overflow-hidden">
                                        <img 
                                            src="https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=400&h=200&fit=crop" 
                                            alt="Pizza gourmet"
                                            class="w-full h-full object-cover"
                                        >
                                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                        <div class="absolute bottom-2 left-3 right-3">
                                            <span class="px-2 py-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full">🔥 -20% HOY</span>
                                        </div>
                                    </div>

                                    {{-- Lista de Productos --}}
                                    <div class="p-3 space-y-2">
                                        <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lo más vendido</h3>
                                        
                                        {{-- Producto 1 --}}
                                        <div class="flex items-center gap-2.5 bg-white rounded-xl p-2 shadow-sm border border-gray-100">
                                            <div class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0">
                                                <img src="https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=100&h=100&fit=crop" alt="Pizza" class="w-full h-full object-cover">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-semibold text-slate-800 truncate">Pizza Margherita</div>
                                                <div class="text-[10px] text-slate-500">Tomate, mozzarella</div>
                                                <div class="text-xs font-bold text-indigo-600 mt-0.5">L. 189</div>
                                            </div>
                                            <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                            </div>
                                        </div>

                                        {{-- Producto 2 --}}
                                        <div class="flex items-center gap-2.5 bg-white rounded-xl p-2 shadow-sm border border-gray-100">
                                            <div class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0">
                                                <img src="https://images.unsplash.com/photo-1513104890138-7c749659a591?w=100&h=100&fit=crop" alt="Pizza Pepperoni" class="w-full h-full object-cover">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-semibold text-slate-800 truncate">Pizza Pepperoni</div>
                                                <div class="text-[10px] text-slate-500">Extra queso</div>
                                                <div class="text-xs font-bold text-indigo-600 mt-0.5">L. 219</div>
                                            </div>
                                            <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                            </div>
                                        </div>

                                        {{-- Producto 3 --}}
                                        <div class="flex items-center gap-2.5 bg-white rounded-xl p-2 shadow-sm border border-gray-100">
                                            <div class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0">
                                                <img src="https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=100&h=100&fit=crop" alt="Hamburguesa" class="w-full h-full object-cover">
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-semibold text-slate-800 truncate">Burger Deluxe</div>
                                                <div class="text-[10px] text-slate-500">Doble carne</div>
                                                <div class="text-xs font-bold text-indigo-600 mt-0.5">L. 165</div>
                                            </div>
                                            <div class="w-7 h-7 bg-indigo-100 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- WhatsApp Sticky Button --}}
                                    <div class="absolute bottom-4 left-3 right-3">
                                        <div class="bg-[#25D366] text-white text-center py-3 rounded-2xl font-bold text-sm shadow-lg shadow-green-500/30 flex items-center justify-center gap-2">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                            </svg>
                                            <span>Pedir por WhatsApp</span>
                                        </div>
                                    </div>
                                    
                                    {{-- Home Indicator --}}
                                    <div class="absolute bottom-1 left-1/2 -translate-x-1/2 w-28 h-1 bg-slate-300 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Animation classes now defined in tailwind.config.js --}}

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- SECCIÓN DE BENEFICIOS --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24 bg-white border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                    Todo lo que necesitas para vender más
                </h2>
                <p class="mt-4 text-lg text-slate-600 max-w-2xl mx-auto">
                    Sin comisiones ocultas. Sin conocimientos técnicos. Solo resultados.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                {{-- Beneficio 1 --}}
                <div class="bg-gradient-to-br from-indigo-50 to-white rounded-2xl p-8 border border-indigo-100">
                    <div class="w-14 h-14 bg-indigo-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Ultra Rápido</h3>
                    <p class="text-slate-600">
                        Tu catálogo carga en menos de 2 segundos. Tus clientes no esperan, compran.
                    </p>
                </div>

                {{-- Beneficio 2 --}}
                <div class="bg-gradient-to-br from-green-50 to-white rounded-2xl p-8 border border-green-100">
                    <div class="w-14 h-14 bg-green-600 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">WhatsApp Directo</h3>
                    <p class="text-slate-600">
                        Los pedidos llegan directo a tu WhatsApp. Sin intermediarios, sin comisiones.
                    </p>
                </div>

                {{-- Beneficio 3 --}}
                <div class="bg-gradient-to-br from-amber-50 to-white rounded-2xl p-8 border border-amber-100">
                    <div class="w-14 h-14 bg-amber-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Gratis para Siempre</h3>
                    <p class="text-slate-600">
                        Empieza sin pagar. Actualiza a PRO solo cuando quieras herramientas avanzadas.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- CTA FINAL --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <section class="py-24 bg-slate-900">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight">
                ¿Listo para vender más?
            </h2>
            <p class="mt-6 text-xl text-slate-400 max-w-2xl mx-auto">
                Unete a los negocios que estan ordenando su presencia digital con Dovra AI.
            </p>
            
            <a 
                href="{{ route('tenant.register') }}"
                class="inline-flex items-center justify-center gap-2 h-16 px-10 mt-10 rounded-full bg-white hover:bg-slate-100 text-slate-900 font-bold text-lg transition-all transform hover:-translate-y-1 shadow-2xl"
            >
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 9a.75.75 0 00-1.5 0v2.25H9a.75.75 0 000 1.5h2.25V15a.75.75 0 001.5 0v-2.25H15a.75.75 0 000-1.5h-2.25V9z" clip-rule="evenodd" />
                </svg>
                Crear Mi Tienda Ahora
            </a>

            <p class="mt-6 text-sm text-slate-500">
                Configuración en 5 minutos • Sin tarjeta de crédito
            </p>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    {{-- FOOTER --}}
    {{-- ═══════════════════════════════════════════════════════════════════════ --}}
    <footer class="bg-slate-950 py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">V</span>
                    </div>
                    <span class="font-bold text-xl text-white">Dovra AI</span>
                </div>
                
                <div class="flex items-center gap-6 text-sm text-slate-400">
                    <a href="{{ route('directory.index') }}" class="hover:text-white transition-colors">Directorio</a>
                    <a href="/login" class="hover:text-white transition-colors">Acceso</a>
                    <a href="{{ route('tenant.register') }}" class="hover:text-white transition-colors">Registrar negocio</a>
                </div>

                <p class="text-sm text-slate-500">
                    © {{ date('Y') }} Dovra AI. La vitrina digital para negocios locales.
                </p>
            </div>
        </div>
    </footer>
</div>
