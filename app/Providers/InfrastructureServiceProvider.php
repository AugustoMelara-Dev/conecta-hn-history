<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Inventory\Contracts\InventoryHoldPortInterface as TransactionalInventoryHoldPortInterface;
use App\Application\Sales\Contracts\CheckoutSessionPortInterface;
use App\Application\Sales\Contracts\InventoryHoldPortInterface;
use App\Contracts\Scope\TenantContextInterface;
use App\Domain\Common\Contracts\SnapshotRepositoryInterface;
use App\Domain\Inventory\Contracts\LocationReaderInterface;
use App\Infrastructure\Common\Snapshots\DatabaseSnapshotRepository;
use App\Infrastructure\Inventory\Adapters\EloquentInventoryHoldAdapter as TransactionalInventoryHoldAdapter;
use App\Infrastructure\Sales\Adapters\EloquentCheckoutSessionAdapter;
use App\Infrastructure\Sales\Adapters\EloquentInventoryHoldAdapter;
use App\Infrastructure\Sales\Adapters\EloquentLocationReader;
use App\Infrastructure\Scope\FilamentTenantContext;
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
            \App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentTenantRepository::class
        );

        $this->app->bind(
            \App\Domain\Identity\Repositories\UserRepositoryInterface::class,
            \App\Infrastructure\Identity\Persistence\EloquentUserRepository::class
        );

        $this->app->bind(
            \App\Domain\Security\Contracts\HasherInterface::class,
            \App\Infrastructure\Security\LaravelHasher::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\LoggerInterface::class,
            \App\Infrastructure\Logging\LaravelLoggerAdapter::class
        );

        $this->app->bind(
            \App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentSocialIdentityRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PAYMENT GATEWAY BINDING (ACL Pattern)
        // ═══════════════════════════════════════════════════════════════════════
        // StripePaymentGateway wraps all Stripe SDK calls and translates
        // vendor-specific exceptions to Domain exceptions. Swap with
        // FakePaymentGateway for testing or PayPalPaymentGateway for migration.
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Billing\Gateways\PaymentGatewayInterface::class,
            fn () => app()->environment('testing')
                ? new \App\Infrastructure\Payments\FakePaymentGateway
                : new \App\Infrastructure\Payments\Stripe\StripePaymentGateway
        );

        $this->app->singleton(\App\Infrastructure\Shared\Context\IdempotencyContext::class);

        $this->app->bind(
            \App\Application\Shared\Contracts\SchemaValidatorInterface::class,
            \App\Infrastructure\Shared\Services\JsonSchemaValidatorService::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\OutboxRepositoryInterface::class,
            function ($app) {
                $inner = $app->make(\App\Infrastructure\Shared\Persistence\EloquentOutboxRepository::class);
                $validator = $app->make(\App\Application\Shared\Contracts\SchemaValidatorInterface::class);

                return new \App\Infrastructure\Shared\Persistence\Decorators\SchemaValidatingOutboxRepository(
                    $inner,
                    $validator
                );
            }
        );

        // ═══════════════════════════════════════════════════════════════════════
        // CONTROLLER DECOUPLING BINDINGS (Hexagonal Architecture)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Application\Shared\Contracts\ReferenceDataReaderInterface::class,
            \App\Infrastructure\Persistence\Readers\EloquentReferenceDataReader::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\ServiceRepositoryInterface::class,
            \App\Infrastructure\Tenant\EloquentServiceRepository::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\ServiceProviderRepositoryInterface::class,
            \App\Infrastructure\Tenant\EloquentServiceProviderRepository::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\AppointmentRepositoryInterface::class,
            \App\Infrastructure\Tenant\EloquentAppointmentRepository::class
        );

        $this->app->bind(
            \App\Application\Tenant\Contracts\TenantProfileWriterInterface::class,
            \App\Infrastructure\Tenant\EloquentTenantProfileWriter::class
        );

        $this->app->bind(
            \App\Application\Catalog\Contracts\MenuSectionWriterInterface::class,
            \App\Infrastructure\Catalog\EloquentMenuSectionWriter::class
        );

        $this->app->bind(
            \App\Application\TenantGovernance\Contracts\TenantCampaignFinderInterface::class,
            \App\Infrastructure\TenantGovernance\Campaign\EloquentTenantCampaignFinder::class
        );

        $this->app->bind(
            \App\Application\TenantGovernance\Contracts\TenantCustomerInboxNoteFinderInterface::class,
            \App\Infrastructure\TenantGovernance\Inbox\EloquentTenantCustomerInboxNoteFinder::class
        );

        $this->app->bind(
            \App\Application\TenantGovernance\Contracts\TenantGrowthSignalsViewInterface::class,
            \App\Infrastructure\TenantGovernance\TenantGrowthSignalsView::class
        );

        $this->app->bind(
            \App\Application\TenantGovernance\Contracts\TenantOperationalComparisonsViewInterface::class,
            \App\Infrastructure\TenantGovernance\TenantOperationalComparisonsView::class
        );

        $this->app->bind(
            \App\Application\TenantGovernance\Contracts\TenantJourneyAnalyticsViewInterface::class,
            \App\Infrastructure\TenantGovernance\TenantJourneyAnalyticsView::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // UPCASTING INFRASTRUCTURE (Resilient Read)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->singleton(\App\Infrastructure\Shared\Services\Upcasting\UpcasterChain::class);

        // Register Upcasters (Ideally this should be in a dedicated config or provider)
        $this->app->resolving(\App\Infrastructure\Shared\Services\Upcasting\UpcasterChain::class, function ($chain) {
            $chain->register(
                'App\Domain\Identity\Events\UserRegistered',
                '1.0',
                new \App\Infrastructure\Shared\Services\Upcasting\Upcasters\UserRegisteredV1toV2Upcaster
            );
        });

        $this->app->bind(
            \App\Domain\Billing\Repositories\PaymentRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentPaymentRepository::class
        );

        $this->app->bind(
            \App\Application\Auth\Contracts\AuthTokenServiceInterface::class,
            \App\Infrastructure\Auth\SanctumAuthTokenService::class
        );

        $this->app->bind(
            TransactionalInventoryHoldPortInterface::class,
            TransactionalInventoryHoldAdapter::class
        );

        $this->app->bind(
            InventoryHoldPortInterface::class,
            EloquentInventoryHoldAdapter::class
        );

        $this->app->bind(
            CheckoutSessionPortInterface::class,
            EloquentCheckoutSessionAdapter::class
        );

        $this->app->bind(
            SnapshotRepositoryInterface::class,
            DatabaseSnapshotRepository::class
        );

        $this->app->bind(
            LocationReaderInterface::class,
            EloquentLocationReader::class
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
            return;
        }

        if ($this->app->environment('local') && $this->app->bound('request')) {
            $request = $this->app->make('request');

            if ($request instanceof Request && filled($request->getSchemeAndHttpHost())) {
                URL::forceRootUrl($request->getSchemeAndHttpHost());
            }
        }
    }

    /**
     * Configure Vite settings.
     */
    protected function configureVite(): void
    {
        Vite::prefetch(concurrency: 3);
        Vite::createAssetPathsUsing(
            static fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/')
        );

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

        // 4. VERIFICATION LIMIT: Prevent Code Guessing
        // 5 attempts per minute per User (Verifying Code)
        RateLimiter::for('otp-verify', function (Request $request) {
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

        // 9. API VERIFICATION: Coupon validation, OTP verify, identity checks
        // 10 attempts per minute per user/IP (stricter than global api)
        RateLimiter::for('api.verification', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // 10. API OTP GENERATION: Prevent SMS bombing / cost overflow
        // 3 attempts per minute per IP (same policy as otp-request)
        RateLimiter::for('api.otp-generation', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // 11. API TRANSACTIONS: Order creation, payment submission
        // 20 attempts per minute per user/IP
        RateLimiter::for('api.transactions', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
