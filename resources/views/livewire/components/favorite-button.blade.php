{{--
    FavoriteButton - Reactive Favorite Toggle with Auth Interception
    
    Usage:
    <livewire:components.favorite-button :model="$tenant" />
    <livewire:components.favorite-button :model="$product" size="sm" :showCount="false" />
--}}

<div class="inline-flex items-center gap-1.5">
    @auth
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- AUTHENTICATED: Functional Button --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <button
            wire:click="toggle"
            wire:loading.attr="disabled"
            type="button"
            class="group relative flex items-center justify-center transition-all duration-200 focus:outline-none
                {{ $size === 'sm' ? 'p-1.5' : ($size === 'lg' ? 'p-3' : 'p-2') }}
                {{ $isFavorite ? 'text-red-500' : 'text-gray-400 hover:text-red-400' }}"
            title="{{ $isFavorite ? 'Quitar de favoritos' : 'Añadir a favoritos' }}"
        >
            {{-- Heart Icon with Optimistic UI --}}
            <span 
                class="transition-transform duration-200 group-active:scale-90"
                wire:loading.class="animate-pulse"
            >
                @if($isFavorite)
                    {{-- Filled Heart --}}
                    <svg 
                        class="{{ $size === 'sm' ? 'w-5 h-5' : ($size === 'lg' ? 'w-8 h-8' : 'w-6 h-6') }}"
                        fill="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                    </svg>
                @else
                    {{-- Outline Heart --}}
                    <svg 
                        class="{{ $size === 'sm' ? 'w-5 h-5' : ($size === 'lg' ? 'w-8 h-8' : 'w-6 h-6') }}"
                        fill="none" 
                        stroke="currentColor" 
                        stroke-width="1.5"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                    </svg>
                @endif
            </span>

            {{-- Ripple effect on click --}}
            <span 
                class="absolute inset-0 rounded-full bg-red-500/20 scale-0 transition-transform duration-300"
                wire:loading.class="scale-100"
            ></span>
        </button>
    @else
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- GUEST: Interceptor Button (Opens Auth Modal) --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <button
            @click="$dispatch('open-auth-modal', { action: 'guardar este favorito' })"
            type="button"
            class="group flex items-center justify-center text-gray-400 hover:text-red-400 transition-all duration-200 focus:outline-none
                {{ $size === 'sm' ? 'p-1.5' : ($size === 'lg' ? 'p-3' : 'p-2') }}"
            title="Añadir a favoritos"
        >
            {{-- Outline Heart --}}
            <span class="transition-transform duration-200 group-active:scale-90">
                <svg 
                    class="{{ $size === 'sm' ? 'w-5 h-5' : ($size === 'lg' ? 'w-8 h-8' : 'w-6 h-6') }}"
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="1.5"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
            </span>
        </button>
    @endauth

    {{-- Favorites Count --}}
    @if($showCount && $count > 0)
        <span class="text-sm font-medium {{ $isFavorite ? 'text-red-500' : 'text-gray-500' }}">
            {{ $count > 999 ? number_format($count / 1000, 1) . 'k' : $count }}
        </span>
    @endif
</div>
