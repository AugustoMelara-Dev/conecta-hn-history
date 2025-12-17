<?php

use App\Jobs\AuditTenantLimits;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\HydrateLocationFromCookie::class, // DIRECTIVE 2: Sticky persistence
            \App\Http\Middleware\CheckMaintenanceMode::class, // Kill Switch Inteligente
        ]);

        $middleware->alias([
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'track.tenant' => \App\Http\Middleware\TrackTenantView::class,
            'track.visits' => \App\Http\Middleware\TrackVisits::class, // Analytics con debouncing
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class, // Alias para uso granular
        ]);

        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // Pilar 4: Audit tenant product limits daily at 2am
        $schedule->job(new AuditTenantLimits)->daily()->at('02:00');

        // Pilar 6: Automation
        // 1. Gestión de Suscripciones (Diario a medianoche)
        $schedule->command('subscriptions:expire')->daily();

        // 2. Limpieza de OTPs (Cada hora)
        $schedule->command('auth:clear-otps')->hourly();

        // 3. Higiene de Archivos (Semanal, domingos a las 3 AM)
        $schedule->command('media:clean-orphans')->weekly()->sundays()->at('03:00');

        // Pilar 8: Analytics Sync (Daily at 00:05 AM)
        $schedule->job(new \App\Jobs\SyncAnalyticsJob)->dailyAt('00:05');

        // ═══════════════════════════════════════════════════════════════════
        // PILAR SEO: Generación automática de sitemap (Diario a medianoche)
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('seo:generate-sitemap')->daily()->at('00:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
