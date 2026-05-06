<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- Security: CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta --}}
    <title>{{ $title ?? 'Conecta HN - Dashboard' }}</title>
    
    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Fonts: Inter (preconnect for performance) --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    {{-- Styles & Scripts --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="antialiased bg-gray-50 flex h-screen overflow-hidden">
    {{-- Sidebar Placeholders (To be implemented or included) --}}
    <div class="hidden md:flex md:w-64 md:flex-col bg-white border-r border-gray-200">
        <div class="flex items-center justify-center h-16 border-b border-gray-200">
             <span class="text-xl font-bold text-gray-900">Conecta<span class="text-blue-600">HN</span></span>
        </div>
        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
             <a href="/app" class="group flex items-center px-2 py-2 text-sm font-medium rounded-md bg-gray-100 text-gray-900">
                Dashboard
             </a>
             {{-- Add more sidebar links here --}}
        </nav>
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center">
                 <div class="ml-3">
                     <p class="text-sm font-medium text-gray-700">{{ Auth::user()->name ?? 'Usuario' }}</p>
                     <p class="text-xs text-gray-500">Ver perfil</p>
                 </div>
            </div>
        </div>
    </div>

    {{-- Main Content Area --}}
    <div class="flex-1 flex flex-col overflow-hidden">
        {{-- Top App Bar (Mobile) --}}
        <header class="bg-white shadow-sm md:hidden p-4 flex justify-between items-center">
             <span class="text-xl font-bold text-gray-900">Conecta<span class="text-blue-600">HN</span></span>
             <button class="text-gray-500">Menu</button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            {{ $slot }}
        </main>
    </div>

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
        </div>
    @endif

    @livewireScripts
    @stack('scripts')
</body>
</html>
