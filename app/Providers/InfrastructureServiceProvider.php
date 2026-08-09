<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Auth\Contracts\AuthTokenServiceInterface;
use App\Application\Shared\Contracts\ReferenceDataReaderInterface;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Application\Shared\Contracts\SocialIdentityRepositoryInterface;
use App\Contracts\Scope\TenantContextInterface;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Common\Contracts\SnapshotStrategyInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Payments\Gateways\PaymentGatewayInterface;
use App\Domain\Security\Contracts\HasherInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Auth\SanctumAuthTokenService;
use App\Infrastructure\Common\Snapshots\DatabaseSnapshotRepository;
use App\Infrastructure\Common\Snapshots\Strategies\EventCountSnapshotStrategy;
use App\Infrastructure\Identity\Persistence\EloquentUserRepository;
use App\Infrastructure\Payments\FakePaymentGateway;
use App\Infrastructure\Payments\Stripe\StripePaymentGateway;
use App\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Infrastructure\Persistence\EloquentSocialIdentityRepository;
use App\Infrastructure\Persistence\Readers\EloquentReferenceDataReader;
use App\Infrastructure\Repositories\TenantGovernance\EloquentTenantRepository;
use App\Infrastructure\Scope\FilamentTenantContext;
use App\Infrastructure\Security\LaravelHasher;
use App\Infrastructure\Shared\Context\IdempotencyContext;
use App\Infrastructure\Shared\Services\JsonSchemaValidatorService;
use App\Infrastructure\Shared\Services\Upcasting\UpcasterChain;
use App\Infrastructure\Shared\Services\Upcasting\Upcasters\UserRegisteredV1toV2Upcaster;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class InfrastructureServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // TENANT CONTEXT ABSTRACTION (Dependency Inversion Principle)
        // ═══════════════════════════════════════════════════════════════════════
        // This binding allows Models and Scopes to resolve tenant context
        // WITHOUT knowing about Filament. The FilamentTenantContext adapter
        // encapsulates all UI-layer coupling in a single, replaceable class.
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->singleton(TenantContextInterface::class, FilamentTenantContext::class);

        // ═══════════════════════════════════════════════════════════════════════
        // REPOSITORY PATTERN BINDINGS (ORM Decoupling)
        // ═══════════════════════════════════════════════════════════════════════
        // These bindings allow Application Layer (Jobs, Actions) to depend on
        // Domain interfaces rather than Eloquent directly.
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            TenantRepositoryInterface::class,
            EloquentTenantRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        $this->app->bind(
            SocialIdentityRepositoryInterface::class,
            EloquentSocialIdentityRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PAYMENT GATEWAY BINDING (ACL Pattern)
        // ═══════════════════════════════════════════════════════════════════════
        // StripePaymentGateway wraps all Stripe SDK calls and translates
        // vendor-specific exceptions to Domain exceptions. Swap with
        // FakePaymentGateway for testing or PayPalPaymentGateway for migration.
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            PaymentGatewayInterface::class,
            fn () => app()->environment('testing')
                ? new FakePaymentGateway
                : new StripePaymentGateway
        );

        $this->app->singleton(IdempotencyContext::class);

        $this->app->bind(
            SchemaValidatorInterface::class,
            JsonSchemaValidatorService::class
        );

        // NOTE: OutboxRepositoryInterface binding is centralized in AppServiceProvider.

        // ═══════════════════════════════════════════════════════════════════════
        // CONTROLLER DECOUPLING BINDINGS (Hexagonal Architecture)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ReferenceDataReaderInterface::class,
            EloquentReferenceDataReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // UPCASTING INFRASTRUCTURE (Resilient Read)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->singleton(UpcasterChain::class);

        // Register Upcasters (Ideally this should be in a dedicated config or provider)
        $this->app->resolving(UpcasterChain::class, function ($chain) {
            $chain->register(
                'App\Domain\Identity\Events\UserRegistered',
                '1.0',
                new UserRegisteredV1toV2Upcaster
            );
        });

        $this->app->bind(
            PaymentRepositoryInterface::class,
            EloquentPaymentRepository::class
        );

        $this->app->bind(
            AuthTokenServiceInterface::class,
            SanctumAuthTokenService::class
        );

        // Tenant registration password hashing contract
        $this->app->bind(
            HasherInterface::class,
            LaravelHasher::class
        );

        // Event-sourcing snapshot infrastructure
        $this->app->bind(
            SnapshotRepositoryInterface::class,
            DatabaseSnapshotRepository::class
        );
        $this->app->bind(
            SnapshotStrategyInterface::class,
            fn () => new EventCountSnapshotStrategy(
                (int) config('eventsourcing.snapshot_threshold', 50)
            )
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureStrictModels();
        $this->configureSecurity();
        $this->configureVite();
        $this->configureRateLimiters();
    }

    /**
     * Configure Eloquent Strict Mode.
     * Checks for:
     * - Lazy Loading (N+1 queries)
     * - Mass Assignment protection
     * - Accessing missing attributes
     */
    protected function configureStrictModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Exception handling for Lazy Loading in Production (log instead of crash)
        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
                $class = get_class($model);
                Log::warning("N+1 Query detected in production: {$class}::{$relation}");
            });
        }
    }

    /**
     * Configure Transport Security and CSP.
     */
    protected function configureSecurity(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure Vite settings.
     */
    protected function configureVite(): void
    {
        Vite::prefetch(concurrency: 3);

        // Use CSP Nonce for scripts if requested
        Vite::useCspNonce();

        // Blade directive for simpler usage
        Blade::directive('cspnonce', function () {
            return '<?php echo Vite::cspNonce(); ?>';
        });
    }

    /**
     * Configure granular rate limiters for API security.
     *
     * ARCHITECTURE: Named Rate Limiters for Traffic Shaping
     * Each limiter targets a specific attack vector with mathematically
     * chosen thresholds based on legitimate human behavior patterns.
     */
    protected function configureRateLimiters(): void
    {
        // 1. GLOBAL API LIMIT: 60 requests/minute per user/IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 2. AUTH LIMIT: Prevent Brute Force
        // 5 attempts per minute (Standard Login)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->email.$request->ip());
        });

        // 3. OTP LIMIT: Prevent SMS Bombing / Cost overflow
        // 3 attempts per minute per IP (Requesting OTP)
        RateLimiter::for('otp-request', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('api.otp-generation', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });

        // 4. VERIFICATION LIMIT: Prevent Code Guessing
        // 5 attempts per minute per User (Verifying Code)
        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api.verification', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // 5. REGISTRATION LIMIT: Prevent Bot Spam Accounts
        // 3 registrations per hour per IP
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // 6. SEARCH LIMIT: Prevent Database DoS
        // 30 searches per minute
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // 7. UPLOAD LIMIT: Prevent Storage DoS
        // 10 uploads per minute
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // 8. NOTIFICATION POLLING
        RateLimiter::for('notifications', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api.transactions', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api.ai-tools', function (Request $request) {
            $user = $request->user();
            $tenantContext = app(TenantContext::class);
            $tenantId = $tenantContext->isInitialized()
                ? $tenantContext->id()
                : ($user?->tenant_id === null ? 'no-tenant' : (string) $user->tenant_id);

            return Limit::perMinute(max(
                1,
                (int) config('ai.tool_confirmations.requests_per_minute', 10),
            ))->by(($user?->getAuthIdentifier() ?: 'guest').'|'.$tenantId.'|'.$request->ip());
        });
    }
}
