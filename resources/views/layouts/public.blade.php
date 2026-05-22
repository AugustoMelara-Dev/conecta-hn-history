<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    {{-- Security: CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @include('components.seo-meta')
    
    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    
    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    {{-- Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Livewire styles removed - migrated to React/Inertia --}}
    
    {{-- Custom Styles Stack --}}
    @stack('styles')
    
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 antialiased">
    {{-- Contenido Principal --}}
    <main>
        {{ $slot }}
    </main>

    {{-- AuthModal removed - migrated to React GlobalLoginModal --}}

    {{-- Global Toast Notifications --}}
    @if(session('toast'))
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            class="fixed bottom-4 right-4 z-50 px-6 py-3 bg-gray-900 text-white rounded-xl shadow-2xl flex items-center gap-3"
        >
            <span>{{ session('toast') }}</span>
            <button @click="show = false" class="text-gray-400 hover:text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif
    
    {{-- Footer Simple --}}
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                {{-- Logo y Descripcion --}}
                <div class="md:col-span-2">
                    <h3 class="text-2xl font-bold mb-4">
                        Conecta <span class="text-blue-400">HN</span>
                    </h3>
                    <p class="text-gray-400 max-w-md">
                        Directorio publico y sistema operativo comercial para negocios locales.
                        Ayudamos a negocios locales a vender, reservar o captar clientes desde una sola plataforma.
                    </p>
                </div>
                
                {{-- Enlaces Rapidos --}}
                <div>
                    <h4 class="font-semibold mb-4">Enlaces</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="/" wire:navigate class="hover:text-white transition-colors">Inicio</a></li>
                        <li><a href="{{ route('tenant.register') }}" wire:navigate class="hover:text-white transition-colors">Registrar Negocio</a></li>
                        <li><a href="/directorio" wire:navigate class="hover:text-white transition-colors">Directorio</a></li>
                        <li><a href="/login" wire:navigate class="hover:text-white transition-colors">Iniciar Sesion</a></li>
                    </ul>
                </div>
                
                {{-- Contacto --}}
                <div>
                    <h4 class="font-semibold mb-4">Contacto</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>Contacta soporte desde tu panel</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                            </svg>
                            <a href="https://wa.me/50499999999" class="hover:text-white transition-colors">
                                WhatsApp
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            {{-- Copyright --}}
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-500 text-sm">
                <p>&copy; {{ date('Y') }} Conecta HN. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
    
    {{-- Livewire scripts removed - migrated to React/Inertia --}}
    
    {{-- Custom Scripts Stack --}}
    @stack('scripts')
</body>
</html>
