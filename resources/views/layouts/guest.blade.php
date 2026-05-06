<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- Security: CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta --}}
    <title>{{ $title ?? 'Conecta HN' }}</title>
    <meta name="description" content="{{ $description ?? 'Conecta HN - El directorio más completo de Honduras' }}">
    
    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Fonts: Inter (preconnect for performance) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    {{-- Styles & Scripts --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Livewire styles removed - migrated to React/Inertia --}}

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="antialiased bg-gray-50">
    {{-- Main Content (no navigation for guest pages) --}}
    {{ $slot }}

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

    {{-- AuthModal removed - migrated to React GlobalLoginModal --}}

    {{-- Livewire scripts removed - migrated to React/Inertia --}}
    @stack('scripts')
</body>
</html>
