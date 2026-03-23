<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @php($assetBase = request()?->getSchemeAndHttpHost() ?: rtrim((string) config('app.url'), '/'))
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Conecta HN') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@700;800&family=Inter:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ $assetBase }}/favicon.svg">
        <link rel="icon" type="image/x-icon" href="{{ $assetBase }}/favicon.ico">

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#0f172a">
        <link rel="apple-touch-icon" href="{{ $assetBase }}/icon-192x192.png">
        <link rel="manifest" href="{{ $assetBase }}/manifest.webmanifest">

        <!-- Inertia Head -->
        @inertiaHead

        <!-- Ziggy Routes -->
        @routes

        <!-- React Fast Refresh -->
        @viteReactRefresh

        <!-- Vite Assets -->
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="antialiased bg-slate-50 dark:bg-slate-900" style="font-family: 'Manrope', 'Segoe UI', sans-serif;">
        @inertia
    </body>
</html>
