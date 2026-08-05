<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Dovra AI') }}</title>

        <!-- Fonts: product typography -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=epilogue:600,700,800|manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#ffffff">
        <link rel="apple-touch-icon" href="/icon-192x192.png">
        <link rel="manifest" href="/build/manifest.webmanifest">

        <!-- Inertia Head -->
        @inertiaHead

        <!-- Ziggy Routes -->
        @routes(null, Vite::cspNonce())

        <!-- React Fast Refresh -->
        @viteReactRefresh

        <!-- Vite Assets -->
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="font-sans antialiased bg-background text-foreground">
        @inertia
    </body>
</html>
