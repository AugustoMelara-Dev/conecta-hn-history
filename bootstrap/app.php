<?php

use App\Domain\TenantGovernance\Exceptions\TenantNotFoundException;
use App\Http\Middleware\ApiLifecycleHeaders;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckTenantStatus;
use App\Http\Middleware\EnsureFeatureRolloutEnabled;
use App\Http\Middleware\EnsureIdempotencyKey;
use App\Http\Middleware\EnsureTenantOwnership;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HydrateLocationFromCookie;
use App\Http\Middleware\IdentifyTenantBySubdomain;
use App\Http\Middleware\InitializeAuthenticatedTenantContext;
use App\Http\Middleware\InitializeFilamentLivewireTenantContext;
use App\Http\Middleware\MeasureHttpPerformance;
use App\Http\Middleware\NormalizeInternalRedirectLocation;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ShareObservabilityContext;
use App\Http\Middleware\TrackAuthenticatedSession;
use App\Http\Middleware\TrackTenantView;
use App\Http\Middleware\TrackTenantVisit;
use App\Http\Middleware\TrackVisits;
use App\Http\Middleware\UseRequestHostSessionDomain;
use App\Http\Middleware\ValidateImpersonationGrant;
use App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob;
use App\Infrastructure\Jobs\Governance\DispatchTenantLimitAudits;
use App\Support\Http\ApiLifecycleHeaderFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Sentry\Laravel\Integration as SentryIntegration;
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
        // Behind Vercel/Railway edges, trust forwarded headers so URL generation
        // and redirect locations use the public host instead of internal hosts.
        $middleware->trustProxies(at: '*');

        $middleware->web(
            prepend: [
                UseRequestHostSessionDomain::class, // Prevent 419 when SESSION_DOMAIN mismatches runtime host
            ],
            append: [
                IdentifyTenantBySubdomain::class, // FIRST: Tenant Context from Subdomain
                ValidateImpersonationGrant::class,
                TrackAuthenticatedSession::class,
                ShareObservabilityContext::class, // Trace + domain context for production observability
                MeasureHttpPerformance::class, // p95-ready structured HTTP metrics behind feature flag
                NormalizeInternalRedirectLocation::class, // Keep proxied redirects same-origin
                SecurityHeaders::class, // Zero-Trust Security Headers
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
                HydrateLocationFromCookie::class, // DIRECTIVE 2: Sticky persistence
                CheckMaintenanceMode::class, // Kill Switch Inteligente
                InitializeFilamentLivewireTenantContext::class, // Rehydrate app-panel Livewire tenant from the authenticated session
            ]
        );

        $middleware->api(append: [
            ShareObservabilityContext::class,
            MeasureHttpPerformance::class,
            SecurityHeaders::class,
        ]);

        $middleware->encryptCookies(except: ['dovra_ai_subject']);

        // Allow first-party Inertia/browser sessions to authenticate same-origin API calls.
        $middleware->statefulApi();

        $middleware->alias([
            'tenant.status' => CheckTenantStatus::class,
            'guest' => RedirectIfAuthenticated::class,
            'track.tenant' => TrackTenantView::class,
            'track.visits' => TrackVisits::class, // Analytics con debouncing
            'track.tenant.visit' => TrackTenantVisit::class, // First-party analytics
            'maintenance' => CheckMaintenanceMode::class, // Alias para uso granular
            'tenant.ownership' => EnsureTenantOwnership::class, // Security: IDOR protection
            'tenant.context' => InitializeAuthenticatedTenantContext::class,
            'idempotency' => EnsureIdempotencyKey::class, // Metadata Transport
            'api.lifecycle' => ApiLifecycleHeaders::class,
            'feature.rollout' => EnsureFeatureRolloutEnabled::class,
        ]);

        //
    })
    ->withSchedule(function (Schedule $schedule) {
        // Pilar 4: Audit tenant product limits daily at 2am
        $schedule->job(new DispatchTenantLimitAudits)->daily()->at('02:00');

        // Pilar 6: Automation
        // 1. GestiÃ³n de Suscripciones (Diario a medianoche)
        $schedule->command('subscriptions:expire')->daily();

        // 2. Limpieza de OTPs (Cada hora)
        $schedule->command('auth:clear-otps')->hourly();

        // 3. Higiene de Archivos (Semanal, domingos a las 3 AM)
        $schedule->command('media:clean-orphans')->weekly()->sundays()->at('03:00');

        // Pilar 8: Analytics Sync (Daily at 00:05 AM)
        $schedule->job(new SyncAnalyticsJob)->dailyAt('00:05');

        // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
        // PILAR SEO: GeneraciÃ³n automÃ¡tica de sitemap (Diario a medianoche)
        // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
        $schedule->command('sitemap:generate')->daily()->at('00:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        if (class_exists(SentryIntegration::class)) {
            SentryIntegration::handles($exceptions);
        }

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request) {
            $response = ApiLifecycleHeaderFactory::apply($request, $response);

            if ($response->isRedirection()) {
                $location = $response->headers->get('Location');
                $targetHost = is_string($location) ? strtolower((string) parse_url($location, PHP_URL_HOST)) : '';

                if (
                    is_string($location)
                    && str_starts_with($location, 'http')
                    && ($targetHost !== '')
                    && (str_ends_with($targetHost, '.up.railway.app') || str_ends_with($targetHost, '.railway.internal'))
                ) {
                    $path = (string) (parse_url($location, PHP_URL_PATH) ?? '/');
                    $query = (string) (parse_url($location, PHP_URL_QUERY) ?? '');
                    $fragment = (string) (parse_url($location, PHP_URL_FRAGMENT) ?? '');

                    $response->headers->set(
                        'Location',
                        $path
                        .($query !== '' ? '?'.$query : '')
                        .($fragment !== '' ? '#'.$fragment : '')
                    );
                }
            }

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

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
