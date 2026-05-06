{{-- 
    AuthModal - B2C "Barrera Invisible"
    
    Comportamiento Responsivo:
    - Mobile: Bottom Sheet (slide up)
    - Desktop: Modal centrado (fade + scale)
--}}

<div
    x-data="{ 
        open: @entangle('isOpen').live,
        mode: @entangle('mode').live
    }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    @keydown.escape.window="open && $wire.closeModal()"
>
    {{-- Backdrop con blur --}}
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="$wire.closeModal()"
        class="absolute inset-0 bg-black/50 backdrop-blur-sm"
    ></div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- MOBILE: Bottom Sheet --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="md:hidden absolute inset-x-0 bottom-0">
        <div
            x-show="open"
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            @click.stop
            class="bg-white rounded-t-3xl shadow-2xl max-h-[90vh] overflow-y-auto"
        >
            {{-- Handle de arrastre visual --}}
            <div class="sticky top-0 bg-white pt-3 pb-2 flex justify-center rounded-t-3xl">
                <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
            </div>

            {{-- Contenido --}}
            <div class="px-6 pb-8">
                @include('livewire.auth.partials.auth-content')
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- DESKTOP: Modal Centrado --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    <div class="hidden md:flex absolute inset-0 items-center justify-center p-4">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.stop
            class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
        >
            {{-- Header con botón de cerrar --}}
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h2 class="text-lg font-bold text-gray-900">
                    {{ $mode === 'login' ? 'Bienvenido de nuevo' : 'Crear cuenta' }}
                </h2>
                <button 
                    @click="$wire.closeModal()"
                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Contenido --}}
            <div class="p-6">
                @include('livewire.auth.partials.auth-content')
            </div>
        </div>
    </div>
</div>
