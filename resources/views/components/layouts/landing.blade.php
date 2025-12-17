<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Conecta HN - Tu Micrositio Profesional' }}</title>
    <meta name="description" content="{{ $description ?? 'Crea tu catálogo digital gratis. Vende más rápido con pedidos directos por WhatsApp.' }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $title ?? 'Conecta HN' }}">
    <meta property="og:description" content="{{ $description ?? 'La vitrina digital de Honduras' }}">
    <meta property="og:type" content="website">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
        /* Patrón de grid sutil */
        .grid-pattern {
            background-image: 
                linear-gradient(rgba(99, 102, 241, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        /* Alpine.js cloak */
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="antialiased">
    {{ $slot }}
    
    @livewireScripts
</body>
</html>
