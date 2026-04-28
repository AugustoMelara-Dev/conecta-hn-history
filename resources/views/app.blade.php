<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Conecta HN') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@700;800&family=Inter:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Favicon (same-origin to avoid Vercel<->Railway CORS drift) -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#0f172a">
        <link rel="apple-touch-icon" href="/icon-192x192.png">
        <link rel="manifest" href="/manifest.webmanifest">

        <!-- Inertia Head -->
        @inertiaHead

        <!-- Ziggy Routes -->
        @php
            $forwardedHost = trim((string) request()->headers->get('x-forwarded-host', ''));
            $forwardedHost = $forwardedHost !== '' ? trim(explode(',', $forwardedHost)[0]) : '';
            $forwardedProto = trim((string) request()->headers->get('x-forwarded-proto', ''));
            $forwardedProto = $forwardedProto !== '' ? strtolower(trim(explode(',', $forwardedProto)[0])) : 'https';
            $configuredOrigin = collect([
                config('app.platform_directory_url'),
                config('app.platform_app_url'),
                'https://vitobusinessos.vercel.app',
                config('app.url'),
                request()->getSchemeAndHttpHost(),
            ])
                ->map(fn ($origin) => trim((string) $origin))
                ->first(fn (string $origin): bool => $origin !== '') ?? '';
            $ziggyOrigin = $forwardedHost !== ''
                ? sprintf('%s://%s', $forwardedProto !== '' ? $forwardedProto : 'https', $forwardedHost)
                : $configuredOrigin;
            $ziggy = new \Tighten\Ziggy\Ziggy(null, $ziggyOrigin);
            $ziggyRouteFunction = config('ziggy.skip-route-function')
                ? ''
                : file_get_contents(base_path('vendor/tightenco/ziggy/dist/route.umd.js'));
        @endphp
        <script type="text/javascript" nonce="{{ app('csp-nonce') }}">const Ziggy=@json($ziggy);{!! $ziggyRouteFunction !!}</script>
        <script type="text/javascript" nonce="{{ app('csp-nonce') }}">
            // Ensure route() resolves against the current origin in proxied deployments.
            if (window.location && window.location.origin) {
                const runtimeOrigin = window.location.origin;
                const ziggyConfig = typeof Ziggy !== 'undefined'
                    ? Ziggy
                    : (window.Ziggy || null);

                if (ziggyConfig && typeof ziggyConfig === 'object') {
                    ziggyConfig.url = runtimeOrigin;
                    ziggyConfig.port = null;
                    window.Ziggy = ziggyConfig;
                }
            }
        </script>

        <!-- React Fast Refresh -->
        @viteReactRefresh

        <!-- Vite Assets -->
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="antialiased bg-slate-50 dark:bg-slate-900" style="font-family: 'Manrope', 'Segoe UI', sans-serif;">
        @inertia
    </body>
</html>
