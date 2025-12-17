{{--
    ═══════════════════════════════════════════════════════════════════════
    MOBILE BOTTOM TAB BAR - iOS Native Experience
    ═══════════════════════════════════════════════════════════════════════
    
    Barra de navegación fija inferior optimizada para "Zona del Pulgar".
    
    Arquitectura Visual:
    1. Glassmorphism (bg-white/85 + backdrop-blur-xl)
    2. 4 columnas: Inicio, Explorar, Favoritos, Perfil
    3. Estados Solid (activo) vs Outline (inactivo)
    4. Safe Area para iPhones modernos
    5. Oculto en desktop (lg:hidden)
    
    Integración:
    - Agregar <x-ui.mobile-nav /> antes de </body> en layout
    - Agregar pb-20 lg:pb-0 al contenedor <main>
    ═══════════════════════════════════════════════════════════════════════
--}}

<nav 
    class="fixed bottom-0 left-0 w-full z-50 lg:hidden
           bg-white/85 backdrop-blur-xl 
           border-t border-gray-200/50 
           shadow-[0_-4px_30px_rgba(0,0,0,0.1)]"
    style="padding-bottom: env(safe-area-inset-bottom, 0px);"
    role="navigation"
    aria-label="Navegación principal móvil"
>
    <div class="grid grid-cols-4 h-16">
        
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 1. INICIO (Home) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @php 
            $isHome = request()->routeIs('home') || request()->is('/') || request()->is(''); 
        @endphp
        <a 
            href="{{ route('home') }}" 
            class="flex flex-col items-center justify-center gap-0.5
                   transition-transform duration-100 active:scale-90
                   {{ $isHome ? 'text-primary-600' : 'text-gray-400' }}"
            aria-current="{{ $isHome ? 'page' : 'false' }}"
        >
            @if($isHome)
                {{-- Icono SOLID (Activo) --}}
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.47 3.84a.75.75 0 011.06 0l8.69 8.69a.75.75 0 101.06-1.06l-8.689-8.69a2.25 2.25 0 00-3.182 0l-8.69 8.69a.75.75 0 001.061 1.06l8.69-8.69z"/>
                    <path d="M12 5.432l8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 01-.75-.75v-4.5a.75.75 0 00-.75-.75h-3a.75.75 0 00-.75.75V21a.75.75 0 01-.75.75H5.625a1.875 1.875 0 01-1.875-1.875v-6.198a2.29 2.29 0 00.091-.086L12 5.43z"/>
                </svg>
            @else
                {{-- Icono OUTLINE (Inactivo) --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
            @endif
            <span class="text-[10px] {{ $isHome ? 'font-medium' : 'font-normal' }}">Inicio</span>
        </a>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 2. EXPLORAR (Directorio) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @php 
            $isExplore = request()->routeIs('directory.*') || request()->is('directorio*'); 
        @endphp
        <a 
            href="{{ route('directory.index') }}" 
            class="flex flex-col items-center justify-center gap-0.5
                   transition-transform duration-100 active:scale-90
                   {{ $isExplore ? 'text-primary-600' : 'text-gray-400' }}"
            aria-current="{{ $isExplore ? 'page' : 'false' }}"
        >
            @if($isExplore)
                {{-- Icono SOLID (Activo) --}}
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 100 13.5 6.75 6.75 0 000-13.5zM2.25 10.5a8.25 8.25 0 1114.59 5.28l4.69 4.69a.75.75 0 11-1.06 1.06l-4.69-4.69A8.25 8.25 0 012.25 10.5z" clip-rule="evenodd"/>
                </svg>
            @else
                {{-- Icono OUTLINE (Inactivo) --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            @endif
            <span class="text-[10px] {{ $isExplore ? 'font-medium' : 'font-normal' }}">Explorar</span>
        </a>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 3. FAVORITOS (Saved) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @php 
            $isFavorites = request()->routeIs('favorites.*') || request()->is('favoritos*');
            $favoritesRoute = Route::has('favorites.index') ? route('favorites.index') : '#';
        @endphp
        <a 
            href="{{ $favoritesRoute }}" 
            class="flex flex-col items-center justify-center gap-0.5
                   transition-transform duration-100 active:scale-90
                   {{ $isFavorites ? 'text-primary-600' : 'text-gray-400' }}"
            aria-current="{{ $isFavorites ? 'page' : 'false' }}"
        >
            @if($isFavorites)
                {{-- Icono SOLID (Activo) --}}
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                </svg>
            @else
                {{-- Icono OUTLINE (Inactivo) --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
            @endif
            <span class="text-[10px] {{ $isFavorites ? 'font-medium' : 'font-normal' }}">Favoritos</span>
        </a>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- 4. PERFIL (Smart Routing) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @php 
            $isProfile = request()->routeIs('profile.*') || request()->is('perfil*', 'admin*', 'app*', 'login*', 'register*');
            
            // Smart Routing Logic
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->tenant) {
                    // Usuario con negocio → Panel Admin
                    $profileRoute = route('filament.app.pages.dashboard', ['tenant' => $user->tenant->slug]);
                    $profileLabel = 'Mi Panel';
                } else {
                    // Usuario sin negocio → Perfil estándar
                    $profileRoute = Route::has('profile.show') ? route('profile.show') : '/perfil';
                    $profileLabel = 'Perfil';
                }
            } else {
                // Invitado → Login
                $profileRoute = route('login');
                $profileLabel = 'Entrar';
            }
        @endphp
        <a 
            href="{{ $profileRoute }}" 
            class="flex flex-col items-center justify-center gap-0.5
                   transition-transform duration-100 active:scale-90
                   {{ $isProfile ? 'text-primary-600' : 'text-gray-400' }}"
            aria-current="{{ $isProfile ? 'page' : 'false' }}"
        >
            @if($isProfile)
                {{-- Icono SOLID (Activo) --}}
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" clip-rule="evenodd"/>
                </svg>
            @else
                {{-- Icono OUTLINE (Inactivo) --}}
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            @endif
            <span class="text-[10px] {{ $isProfile ? 'font-medium' : 'font-normal' }}">{{ $profileLabel }}</span>
        </a>
    </div>
</nav>

{{-- 
═══════════════════════════════════════════════════════════════════════
INSTRUCCIONES DE INTEGRACIÓN
═══════════════════════════════════════════════════════════════════════

1. AGREGAR EN LAYOUT (layouts/app.blade.php o layouts/public.blade.php):
   Justo antes de </body>:
   
   <x-ui.mobile-nav />

2. PROTEGER EL CONTENIDO (Evitar que la barra tape el footer):
   En el elemento <main> del layout, agregar:
   
   <main class="pb-20 lg:pb-0">
       {{ $slot }}
   </main>
   
   Explicación:
   - pb-20 = 80px de padding (cubre la barra de 64px + margen)
   - lg:pb-0 = En desktop, sin padding extra (barra oculta)

3. ASEGURAR EL SAFE AREA EN CSS (Opcional, para iOS):
   Si no funciona automáticamente, añadir en app.css:
   
   @supports (padding-bottom: env(safe-area-inset-bottom)) {
       nav[aria-label="Navegación principal móvil"] {
           padding-bottom: env(safe-area-inset-bottom);
       }
   }

═══════════════════════════════════════════════════════════════════════
--}}
