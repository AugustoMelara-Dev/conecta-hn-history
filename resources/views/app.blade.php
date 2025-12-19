<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Page Title --}}
    <title inertia>{{ config('app.name', 'Conecta HN') }}</title>

    {{-- Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    {{-- Inertia Head Directive (Required for <Head> component in React) --}}
    @inertiaHead

    {{-- Vite: Load React app entry point --}}
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="font-sans antialiased bg-gray-50 dark:bg-gray-900">
    {{-- Inertia App Directive (Required - mounts React app here) --}}
    @inertia
</body>
</html>
