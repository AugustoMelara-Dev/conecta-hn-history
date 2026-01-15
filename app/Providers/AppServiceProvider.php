<?php

declare(strict_types=1);

namespace App\Providers;

// Models
use App\Contracts\Events\DomainEventBus;
use App\Infrastructure\Events\LaravelEventBus;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Lead;
use App\Models\MenuSection;
use App\Models\Order;
// Policies
use App\Models\Product;
use App\Models\Review;
use App\Policies\CampaignPolicy;
use App\Policies\CouponPolicy;
use App\Policies\LeadPolicy;
use App\Policies\MenuSectionPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
// Event Bus Infrastructure
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // TITANIUM EVENT STORE: The One True Store (DB + Outbox)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Common\Contracts\EventStoreRepositoryInterface::class,
            \App\Infrastructure\Common\EventStore\DatabaseEventStore::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // OUTBOX PUBLISHER: Transport Adapter (Log, Redis, etc.)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Infrastructure\Common\Outbox\MessagePublisherInterface::class,
            \App\Infrastructure\Common\Outbox\LogMessagePublisher::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // DOMAIN EVENT BUS: Decouples Domain Layer from Laravel Event System
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(DomainEventBus::class, LaravelEventBus::class);

        // ═══════════════════════════════════════════════════════════════════════
        // SERVICES & CONTRACTS BINDINGS
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Contracts\ReportGenerationInterface::class,
            \App\Services\ReportGenerationService::class
        );
        $this->app->bind(
            \App\Contracts\LoggerInterface::class,
            \App\Services\LaravelLoggerAdapter::class
        );
        $this->app->bind(
            \App\Contracts\DataExportInterface::class,
            \App\Services\ExcelExportService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // NOTIFICATION ABSTRACTION: Decouples Filament from Business Logic
        // ═══════════════════════════════════════════════════════════════════════
        // NOTIFICATION ABSTRACTION: Decouples Filament from Business Logic
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Contracts\AdminNotifier::class,
            \App\Infrastructure\Notifications\FilamentAdminNotifier::class
        );
        $this->app->bind(
            \App\Domain\Contracts\SearchRepository::class,
            \App\Infrastructure\Search\EloquentSearchRepository::class
        );
        $this->app->bind(
            \App\Domain\Directory\Contracts\DirectoryReader::class,
            \App\Infrastructure\Directory\EloquentDirectoryReader::class
        );
        $this->app->bind(
            \App\Domain\Catalog\Contracts\ProductCatalogReader::class,
            \App\Infrastructure\Catalog\CachedProductReader::class
        );
        $this->app->bind(
            \App\Domain\Billing\Contracts\BillingReader::class,
            \App\Infrastructure\Billing\EloquentBillingReader::class
        );
        $this->app->bind(
            \App\Domain\Tenant\Contracts\OnboardingReader::class,
            \App\Infrastructure\Tenant\CachedOnboardingReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SALES DOMAIN: Order Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Sales\Contracts\OrderReader::class,
            \App\Infrastructure\Sales\EloquentOrderReader::class
        );
        $this->app->bind(
            \App\Domain\Sales\Contracts\ReportReader::class,
            \App\Infrastructure\Sales\EloquentReportReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ANALYTICS DOMAIN: Metrics Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Analytics\Contracts\AnalyticsReader::class,
            \App\Infrastructure\Analytics\EloquentAnalyticsReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PRODUCT DOMAIN: Inventory Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Product\Contracts\ProductReader::class,
            \App\Infrastructure\Product\EloquentProductReader::class
        );
        $this->app->bind(
            \App\Domain\Tenant\Contracts\TenantProductRepository::class,
            \App\Infrastructure\Tenant\EloquentTenantProductRepository::class
        );
        // Shared Domains
        $this->app->singleton(
            \App\Domain\Shared\Contracts\TenantContext::class,
            \App\Infrastructure\Shared\RequestTenantContext::class
        );
        $this->app->bind(
            \App\Domain\Shared\Contracts\TenantReadRepository::class,
            \App\Infrastructure\Shared\EloquentTenantReadRepository::class
        );

        // Public/Storefront Domains (Decorated with Cache)
        $this->app->bind(
            \App\Domain\Public\Contracts\TenantStorefrontRepository::class,
            function ($app) {
                // Instantiate the inner Eloquent Implementation
                $inner = $app->make(\App\Infrastructure\Public\EloquentTenantStorefrontRepository::class);
                
                // Wrap it with the Cached Decorator
                return new \App\Infrastructure\Public\CachedTenantStorefrontRepository($inner);
            }
        );
        $this->app->bind(
            \App\Domain\Public\Contracts\LandingPageRepository::class,
            \App\Infrastructure\Public\EloquentLandingPageRepository::class
        );
        $this->app->singleton(
            \App\Domain\Shared\Services\TenantContext::class,
            \App\Domain\Shared\Services\TenantContext::class
        );

        $this->app->bind(
            \App\Domain\Public\Contracts\BuyerRepository::class,
            \App\Infrastructure\Public\EloquentBuyerRepository::class
        );

        // Tenant Domains (Metrics)
        $this->app->bind(
            \App\Domain\Tenant\Contracts\DashboardMetricsRepository::class,
            \App\Infrastructure\Tenant\EloquentDashboardMetricsRepository::class
        );

        // Tenant Domains
        $this->app->bind(
            \App\Domain\Tenant\Contracts\OrderRepository::class,
            \App\Infrastructure\Tenant\EloquentOrderRepository::class
        );
        $this->app->bind(
            \App\Domain\Tenant\Contracts\TenantProductRepository::class,
            \App\Infrastructure\Tenant\EloquentTenantProductRepository::class
        );
        $this->app->bind(
            \App\Domain\Tenant\Contracts\CouponRepository::class,
            \App\Infrastructure\Tenant\EloquentCouponRepository::class
        );

        // API Domains
        $this->app->bind(
             \App\Domain\Api\Contracts\NotificationRepository::class,
             \App\Infrastructure\Api\EloquentNotificationRepository::class
        );
        $this->app->bind(
             \App\Domain\Tenant\Contracts\BillingRepository::class,
             \App\Infrastructure\Tenant\EloquentBillingRepository::class
        );
        $this->app->bind(
            \App\Domain\Marketing\Contracts\CouponReader::class,
            \App\Infrastructure\Marketing\EloquentCouponReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SEARCH & PUBLIC CATALOG
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Search\Contracts\GlobalSearchReader::class,
            \App\Infrastructure\Search\EloquentGlobalSearchReader::class
        );
        $this->app->bind(
            \App\Domain\Catalog\Contracts\PublicCatalogReader::class,
            \App\Infrastructure\Catalog\EloquentPublicCatalogReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // COMMAND BUS: Synchronous Execution Pipeline
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Application\Shared\Contracts\CommandBus::class,
            \App\Infrastructure\Bus\IlluminateCommandBus::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // REPOSITORY BINDINGS: DIP Compliance for Handlers
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Identity\Repositories\UserRepositoryInterface::class,
            \App\Infrastructure\Repositories\Identity\EloquentUserRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentTenantRepository::class
        );
        $this->app->bind(
            \App\Domain\Billing\Repositories\PaymentRepositoryInterface::class,
            \App\Infrastructure\Repositories\Billing\EloquentPaymentRepository::class
        );
        $this->app->bind(
            \App\Domain\Sales\Repositories\OrderRepositoryInterface::class,
            \App\Infrastructure\Repositories\Sales\EloquentOrderRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // REVIEWS DOMAIN: Repository Bindings
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Reviews\Repositories\ReviewRepositoryInterface::class,
            \App\Infrastructure\Repositories\Reviews\EloquentReviewRepository::class
        );
        $this->app->bind(
            \App\Domain\Product\Repositories\ProductRepositoryInterface::class,
            \App\Infrastructure\Repositories\Products\EloquentProductRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PAYMENT DOMAIN: Repository Bindings
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Payment\Repositories\WebhookLogRepositoryInterface::class,
            \App\Infrastructure\Repositories\Payment\EloquentWebhookLogRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // IDENTITY DOMAIN: Social Identity Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface::class,
            \App\Infrastructure\Repositories\Identity\EloquentSocialIdentityRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // DOCUMENT STORAGE: Abstraction for file handling in Billing Domain
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Billing\Services\DocumentStorageServiceInterface::class,
            \App\Infrastructure\Storage\LaravelDocumentStorageService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ANALYTICS DOMAIN: Interaction Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Analytics\Repositories\InteractionRepositoryInterface::class,
            \App\Infrastructure\Repositories\Analytics\EloquentInteractionRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SALES DOMAIN: Lead Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Sales\Repositories\LeadRepositoryInterface::class,
            \App\Infrastructure\Repositories\Sales\EloquentLeadRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // AUTH DOMAIN: OTP Notification and Repository Abstractions
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Auth\Contracts\OtpNotifierInterface::class,
            \App\Infrastructure\Notifications\EmailOtpNotifier::class
        );
        
        // OTP Repository with scalar injection (config-agnostic)
        $this->app->bind(
            \App\Domain\Auth\Repositories\OtpRepositoryInterface::class,
            fn () => new \App\Infrastructure\Repositories\Auth\EloquentOtpRepository(
                defaultExpiryMinutes: (int) config('auth.otp.expiry_minutes', 10)
            )
        );

        // Admin Notifier with pure domain entities
        $this->app->bind(
            \App\Domain\Shared\Contracts\AdminNotifierInterface::class,
            \App\Infrastructure\Notifications\FilamentAdminNotifier::class
        );

        // Admin Notifier with pure domain entities
        $this->app->bind(
            \App\Domain\Shared\Contracts\AdminNotifierInterface::class,
            \App\Infrastructure\Notifications\FilamentAdminNotifier::class
        );

        // System Failure Notifier (SRE Hardening)
        $this->app->bind(
            \App\Domain\Shared\Contracts\FailureNotifierInterface::class,
            \App\Infrastructure\Notifications\LogFailureNotifier::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // BILLING DOMAIN: Subscription Gateway Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Billing\Gateways\SubscriptionGatewayInterface::class,
            \App\Infrastructure\Billing\Gateways\ManualSubscriptionGateway::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SHARED: Media Storage Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Shared\Contracts\MediaStorageServiceInterface::class,
            \App\Infrastructure\Storage\LaravelMediaStorageService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ORDERS DOMAIN: Receipt PDF Generator
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Orders\Contracts\ReceiptGeneratorInterface::class,
            \App\Infrastructure\Reporting\DomPdfReceiptGenerator::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: Rate Limiting Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Domain\Shared\Contracts\RateLimitServiceInterface::class,
            \App\Infrastructure\Security\LaravelRateLimitService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // TRANSACTIONAL OUTBOX: Event Dispatcher with At-Least-Once Delivery
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->singleton(
            \App\Infrastructure\Bus\TransactionalEventDispatcher::class,
            \App\Infrastructure\Bus\TransactionalEventDispatcher::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // FORTIFY ACTIONS: Password Reset & User Authentication
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \Laravel\Fortify\Contracts\ResetsUserPasswords::class,
            \App\Actions\Fortify\ResetPasswordAction::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // IDEMPOTENCY: Titanium Stability
        // ═══════════════════════════════════════════════════════════════════════
        // ═══════════════════════════════════════════════════════════════════════
        // IDENTITY DOMAIN: Defense in Depth (Decorator Pattern)
        // ═══════════════════════════════════════════════════════════════════════
        
        // 1. CONTEXT PROVIDER (Polymorphic Identity)
        $this->app->bind(
            \App\Application\Common\Interfaces\ClientContextProviderInterface::class,
            function ($app) {
                return $app->runningInConsole()
                    ? new \App\Infrastructure\Common\Context\CliClientContextProvider()
                    : new \App\Infrastructure\Common\Context\WebClientContextProvider();
            }
        );

        // 2. DECORATOR STACK (The Onion)
        // Order: Security -> Observability -> Consistency -> Core
        $this->app->bind(
            \App\Application\Identity\Handlers\RegisterUserHandlerInterface::class,
            function ($app) {
                // CORE: The Business Logic (Pure)
                $core = $app->make(\App\Application\Identity\Handlers\RegisterUserHandler::class);

                // LAYER 1: Idempotency (The Guard) - Protects Core from duplicates
                $idempotent = new \App\Application\Identity\Decorators\IdempotentRegisterUserHandler(
                    inner: $core,
                    service: $app->make(\App\Infrastructure\Common\Idempotency\IdempotencyService::class)
                );

                // LAYER 2: Telemetry (The Watcher) - Measures Total Execution (including Idempotency check)
                $telemetry = new \App\Application\Identity\Decorators\TelemetryRegisterUserHandler(
                    inner: $idempotent,
                    logger: $app->make(\Psr\Log\LoggerInterface::class)
                );

                // LAYER 3: Security (The Shield) - OUTERMOST LAYER
                // This runs first! (Fail Fast Principle)
                return new \App\Application\Identity\Decorators\RateLimitedRegisterUserHandler(
                    inner: $telemetry,
                    rateLimiter: $app->make(\Illuminate\Cache\RateLimiter::class),
                    contextProvider: $app->make(\App\Application\Common\Interfaces\ClientContextProviderInterface::class)
                );
            }
        );

        // 3. SALES DOMAIN: Idempotency Protection
        $this->app->bind(
            \App\Application\Sales\Handlers\CreateOrderHandlerInterface::class,
            function ($app) {
                $core = $app->make(\App\Application\Sales\Handlers\CreateOrderHandler::class);

                return new \App\Application\Sales\Decorators\IdempotentCreateOrderHandler(
                    inner: $core,
                    service: $app->make(\App\Infrastructure\Common\Idempotency\IdempotencyService::class)
                );
            }
        );

        // 4. TENANCY DOMAIN: Idempotency Protection
        $this->app->bind(
            \App\Application\Tenant\Handlers\RegisterTenantHandlerInterface::class,
            function ($app) {
                $core = $app->make(\App\Application\Tenant\Handlers\RegisterTenantHandler::class);

                return new \App\Application\Tenant\Decorators\IdempotentRegisterTenantHandler(
                    inner: $core,
                    service: $app->make(\App\Infrastructure\Common\Idempotency\IdempotencyService::class)
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // POLYMORPHIC INTEGRITY: Morph Map for Domain Migration
        // ═══════════════════════════════════════════════════════════════════════
        // CRITICAL: This mapping MUST be registered BEFORE moving model files.
        // It decouples the database's model_type column from the PHP namespace.
        // Without this, activity_log, media, comments and other polymorphic
        // tables would have orphaned records pointing to non-existent classes.
        // ═══════════════════════════════════════════════════════════════════════
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'tenant' => \App\Models\Tenant::class,
            'product' => \App\Models\Product::class,
            'order' => \App\Models\Order::class,
            'coupon' => \App\Models\Coupon::class,
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // DOMAIN MIGRATION: Model Aliases for Gradual Transition
        // ═══════════════════════════════════════════════════════════════════════
        // 80+ files still reference App\Models\User and App\Models\Tenant.
        // These aliases maintain backward compatibility during gradual transition.
        // REMOVE AFTER: 2026-03-05 (60 days post-migration)
        // ═══════════════════════════════════════════════════════════════════════
        $this->registerModelAliases();

        // ═══════════════════════════════════════════════════════════════════════
        // DEPLOYMENT SAFETY: Class Aliases for Migrated Jobs
        // ═══════════════════════════════════════════════════════════════════════
        // Jobs in Redis queue may reference old App\Jobs\* namespace.
        // These aliases ensure pending jobs are correctly resolved during deploy.
        // REMOVE AFTER: 2026-02-05 (30 days post-migration)
        // ═══════════════════════════════════════════════════════════════════════
        $this->registerJobAliases();

        // ═══════════════════════════════════════════════════════════════════════
        // DATA GOVERNANCE: Observers & Global Policies
        // ═══════════════════════════════════════════════════════════════════════
        // We register policies here explicitly to avoid auto-discovery overhead
        // and enforce architectural boundaries.
        // ═══════════════════════════════════════════════════════════════════════

        // Policies for User and Tenant are auto-discovered via Domain structure
        // or explicitly bound here if needed.
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(\App\Models\Tenant::class, TenantPolicy::class);

        // Core Models Policies
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(MenuSection::class, MenuSectionPolicy::class);

        // Observers
        Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: Rate Limiters
        // ═══════════════════════════════════════════════════════════════════════
        \Illuminate\Support\Facades\RateLimiter::for('analytics-protection', function (\Illuminate\Http\Request $request) {
            // Composite Key: IP + User Agent + Target Tenant
            // Prevents botnet distributed attacks against a specific tenant.
            $key = $request->ip() . '|' . $request->userAgent() . '|' . $request->input('tenant_id');
            
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($key);
        });

        // TENANT-AWARE RATE LIMITING (Contextual Throttling)
        \Illuminate\Support\Facades\RateLimiter::for('tenant-aware', function (\Illuminate\Http\Request $request) {
            // Resolve Tenant ID securely from Context if available
            // If explicit 'tenant_id' input is missing, try to resolve from route or attributes
            // Fallback to IP if purely anonymous without context
            
            /** @var \App\Domain\Shared\Contracts\TenantContext $context */
            $context = app(\App\Domain\Shared\Contracts\TenantContext::class);
            $tenantId = $context->getTenantId() ?? $request->input('tenant_id'); // Try context first

            $key = $tenantId 
                ? "tenant_id:{$tenantId}|ip:" . $request->ip() 
                : "ip:" . $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($key); // Increased to 120 as per prompt example
        });

        // ═══════════════════════════════════════════════════════════════════════
        // BUS CONFIGURATION: Middleware & Mappings
        // ═══════════════════════════════════════════════════════════════════════
        \Illuminate\Support\Facades\Bus::pipeThrough([
            \App\Infrastructure\Bus\Middleware\TransactionalMiddleware::class,
        ]);

        \Illuminate\Support\Facades\Bus::map([
            \App\Application\Identity\Commands\RegisterUserCommand::class => \App\Application\Identity\Handlers\RegisterUserHandlerInterface::class,
            \App\Application\Sales\Commands\CreateOrderCommand::class => \App\Application\Sales\Handlers\CreateOrderHandler::class,
            \App\Application\Tenant\Commands\RegisterTenantCommand::class => \App\Application\Tenant\Handlers\RegisterTenantHandler::class,
            // \App\Application\Identity\Commands\RegisterUserCommand::class => \App\Application\Identity\Handlers\RegisterUserHandler::class, (Replaced by Decorator Stack)
            \App\Application\Billing\Commands\ReportPaymentCommand::class => \App\Application\Billing\Handlers\ReportPaymentHandler::class,
            \App\Application\Reviews\Commands\SubmitReviewCommand::class => \App\Application\Reviews\Handlers\SubmitReviewHandler::class,
            \App\Application\Products\Commands\CreateProductCommand::class => \App\Application\Products\Handlers\CreateProductHandler::class,
            \App\Application\Products\Commands\UpdateProductCommand::class => \App\Application\Products\Handlers\UpdateProductHandler::class,
            \App\Application\Products\Commands\DeleteProductCommand::class => \App\Application\Products\Handlers\DeleteProductHandler::class,
            \App\Application\Products\Commands\ToggleProductVisibilityCommand::class => \App\Application\Products\Handlers\ToggleProductVisibilityHandler::class,
            \App\Application\Sales\Commands\CancelOrderCommand::class => \App\Application\Sales\Handlers\CancelOrderHandler::class,
            \App\Application\Payments\Commands\HandlePaymentWebhookCommand::class => \App\Application\Payments\Handlers\HandlePaymentWebhookHandler::class,
            \App\Application\Billing\Commands\RequestUpgradeCommand::class => \App\Application\Billing\Handlers\RequestUpgradeHandler::class,
            \App\Application\Auth\Commands\LoginViaSocialProviderCommand::class => \App\Application\Auth\Handlers\LoginViaSocialProviderHandler::class,
            \App\Application\Auth\Commands\UpdateUserPasswordCommand::class => \App\Application\Auth\Handlers\UpdateUserPasswordHandler::class,
            \App\Application\Auth\Commands\UpdateUserProfileCommand::class => \App\Application\Auth\Handlers\UpdateUserProfileHandler::class,
            \App\Application\Auth\Commands\DeleteUserCommand::class => \App\Application\Auth\Handlers\DeleteUserHandler::class,
            \App\Application\Auth\Commands\GenerateOtpCommand::class => \App\Application\Auth\Handlers\GenerateOtpHandler::class,
            \App\Application\Auth\Commands\VerifyOtpCommand::class => \App\Application\Auth\Handlers\VerifyOtpHandler::class,
            \App\Application\Tenant\Commands\UpdateTenantScheduleCommand::class => \App\Application\Tenant\Handlers\UpdateTenantScheduleHandler::class,
            \App\Application\Tenant\Commands\UpdateLandingPageCommand::class => \App\Application\Tenant\Handlers\UpdateLandingPageHandler::class,
            \App\Application\Tenant\Commands\UpdateTenantBrandingCommand::class => \App\Application\Tenant\Handlers\UpdateTenantBrandingHandler::class,
            \App\Application\Interactions\Commands\RecordInteractionCommand::class => \App\Application\Interactions\Handlers\RecordInteractionHandler::class,
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // EVENT LISTENERS: Eventual Consistency Handlers
        // ═══════════════════════════════════════════════════════════════════════
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Reviews\Events\ReviewCreated::class,
            \App\Application\Reviews\Listeners\UpdateProductRating::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\Order\OrderCancelled::class,
            \App\Application\Sales\Listeners\RestoreStockOnCancellation::class
        );
    }

    /**
     * Register class aliases for migrated Jobs.
     *
     * DEPLOYMENT SAFETY:
     * Jobs serialized in Redis before migration use old App\Jobs\* namespace.
     * These aliases redirect to new App\Domain\*\Jobs\* locations.
     *
     * @deprecated Remove after 2026-02-05 (30 days post-migration)
     */
    /**
     * Register class aliases for migrated Jobs.
     *
     * @deprecated Remove entire method after 2026-03-05 (60 days post-migration).
     *             Once Redis is clear of old payloads, this mapping is tech debt.
     */
    protected function registerJobAliases(): void
    {
        $aliases = [
            'App\\Jobs\\GeneratePdfReportJob' => \App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob::class,
            'App\\Jobs\\GenerateSalesExport' => \App\Infrastructure\Jobs\Exports\GenerateSalesExport::class,
            // REMOVED: 'App\\Jobs\\GenerateSalesReportJob' - Legacy proxy deleted
            // Legacy Root to Application (Double Jump Protection)
            'App\\Jobs\\SendCampaignEmail' => \App\Infrastructure\Jobs\Marketing\SendCampaignEmail::class,
            'App\\Jobs\\GenerateProductBlurhash' => \App\Infrastructure\Jobs\Media\GenerateProductBlurhash::class,
            'App\\Jobs\\EnhanceTenantProfileJob' => \App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob::class,
            'App\\Jobs\\GenerateBusinessProfile' => \App\Infrastructure\Jobs\AI\GenerateBusinessProfile::class,
            'App\\Jobs\\RecordInteraction' => \App\Infrastructure\Jobs\Analytics\RecordInteraction::class,
            'App\\Jobs\\SyncAnalyticsJob' => \App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob::class,
            'App\\Jobs\\ReleaseStaleOrders' => \App\Infrastructure\Jobs\Governance\ReleaseStaleOrders::class,
            'App\\Jobs\\AuditTenantLimits' => \App\Infrastructure\Jobs\Governance\AuditTenantLimits::class,

            // Domain to Application/Infrastructure (The Great Migration)
            'App\\Domain\\Marketing\\Jobs\\SendCampaignEmail' => \App\Infrastructure\Jobs\Marketing\SendCampaignEmail::class,
            'App\\Domain\\Analytics\\Jobs\\RecordInteraction' => \App\Infrastructure\Jobs\Analytics\RecordInteraction::class,
            'App\\Domain\\Analytics\\Jobs\\SyncAnalyticsJob' => \App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob::class,
            'App\\Domain\\Analytics\\Jobs\\SyncTenantAnalytics' => \App\Infrastructure\Jobs\Analytics\SyncTenantAnalytics::class,
            'App\\Domain\\TenantGovernance\\Jobs\\AuditTenantLimits' => \App\Infrastructure\Jobs\Governance\AuditTenantLimits::class,
            'App\\Domain\\TenantGovernance\\Jobs\\ReleaseStaleOrders' => \App\Infrastructure\Jobs\Governance\ReleaseStaleOrders::class,
            'App\\Domain\\AIEnrichment\\Jobs\\EnhanceTenantProfileJob' => \App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob::class,
            'App\\Domain\\AIEnrichment\\Jobs\\GenerateBusinessProfile' => \App\Infrastructure\Jobs\AI\GenerateBusinessProfile::class,
            'App\\Domain\\MediaProcessing\\Jobs\\GenerateProductBlurhash' => \App\Infrastructure\Jobs\Media\GenerateProductBlurhash::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GeneratePdfReportJob' => \App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GenerateSalesExport' => \App\Infrastructure\Jobs\Exports\GenerateSalesExport::class,
        ];

        foreach ($aliases as $oldClass => $newClass) {
            if (! class_exists($oldClass)) {
                class_alias($newClass, $oldClass);
            }
        }
    }

    /**
     * Register class aliases for migrated Models.
     *
     * NOTE: Aliases reversed after Domain Purification (2026-01-07).
     * Now App\Models is canonical, Domain namespace is deprecated.
     *
     * @deprecated Remove after 2026-03-05 (60 days post original migration)
     */
    protected function registerModelAliases(): void
    {
        // MIGRATION COMPLETE: User and Tenant now live in App\Models
        // No aliases needed as App\Models is now the canonical location.
        // This method is retained for documentation purposes only.
    }
}
