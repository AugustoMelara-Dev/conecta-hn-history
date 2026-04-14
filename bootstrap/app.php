<?php

use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob;
use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/api_mock.php');
            require base_path('routes/public.php');
        },
    )
    ->withCommands([
        __DIR__.'/../app/Infrastructure/Console/Commands',
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [
                \App\Http\Middleware\UseRequestHostSessionDomain::class, // Prevent 419 when SESSION_DOMAIN mismatches runtime host
            ],
            append: [
                \App\Http\Middleware\IdentifyTenantBySubdomain::class, // FIRST: Tenant Context from Subdomain
                \App\Http\Middleware\ShareObservabilityContext::class, // Trace + domain context for production observability
                \App\Http\Middleware\SecurityHeaders::class, // Zero-Trust Security Headers
                \App\Http\Middleware\HandleInertiaRequests::class,
                \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
                \App\Http\Middleware\HydrateLocationFromCookie::class, // DIRECTIVE 2: Sticky persistence
                \App\Http\Middleware\CheckMaintenanceMode::class, // Kill Switch Inteligente
            ]
        );

        $middleware->api(append: [
            \App\Http\Middleware\ShareObservabilityContext::class,
        ]);

        $middleware->alias([
            'tenant.status' => \App\Http\Middleware\CheckTenantStatus::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'track.tenant' => \App\Http\Middleware\TrackTenantView::class,
            'track.visits' => \App\Http\Middleware\TrackVisits::class, // Analytics con debouncing
            'track.tenant.visit' => \App\Http\Middleware\TrackTenantVisit::class, // First-party analytics
            'maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class, // Alias para uso granular
            'tenant.ownership' => \App\Http\Middleware\EnsureTenantOwnership::class, // Security: IDOR protection
            'idempotency' => \App\Http\Middleware\EnsureIdempotencyKey::class, // Metadata Transport
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
        $schedule->job(new SyncAnalyticsJob)->dailyAt('00:05');

        // ═══════════════════════════════════════════════════════════════════
        // PILAR SEO: Generación automática de sitemap (Diario a medianoche)
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('seo:generate-sitemap')->daily()->at('00:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (SymfonyResponse $response, \Throwable $exception, Request $request) {
            if (app()->environment(['local', 'testing'])) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419 && $request->header('X-Inertia')) {
                return redirect()
                    ->back(303)
                    ->with('error', 'Tu sesión expiró por seguridad. Recargamos la vista para que continúes.');
            }

            if ($status >= 500 && $request->header('X-Inertia')) {
                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });

        $exceptions->render(function (TenantNotFoundException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tenant not found.',
                ], 404);
            }

            return response()->view('errors.404', [], 404);
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Tu sesión expiró. Recarga la página e intenta de nuevo.',
                ], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('toast', 'Tu sesión expiró por seguridad. Reintentemos desde este paso.');
        });
    })->create();
