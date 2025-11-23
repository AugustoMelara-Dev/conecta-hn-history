<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- SEO: Basic Meta Tags --}}
        <title>{{ $meta['title'] ?? config('app.name', 'Conecta HN') }}</title>
        <meta name="description" content="{{ $meta['description'] ?? 'El mercado digital de Honduras' }}">

        {{-- SEO: Open Graph (Facebook/WhatsApp) --}}
        <meta property="og:type" content="website" />
        <meta property="og:title" content="{{ $meta['title'] ?? config('app.name') }}" />
        <meta property="og:description" content="{{ $meta['description'] ?? 'El mercado digital de Honduras' }}" />
        <meta property="og:image" content="{{ $meta['image'] ?? asset('img/default-og.jpg') }}" />
        <meta property="og:url" content="{{ $meta['url'] ?? url()->current() }}" />

        {{-- SEO: Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $meta['title'] ?? config('app.name') }}" />
        <meta name="twitter:description" content="{{ $meta['description'] ?? 'El mercado digital de Honduras' }}" />
        <meta name="twitter:image" content="{{ $meta['image'] ?? asset('img/default-og.jpg') }}" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
