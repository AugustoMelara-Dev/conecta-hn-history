<?php

declare(strict_types=1);

namespace App\Providers;

// Models
use App\Contracts\Events\DomainEventBus;
use App\Infrastructure\Events\LaravelEventBus;
use App\Models\Appointment;
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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Domain\Common\Contracts\SnapshotStrategyInterface::class,
            fn () => new \App\Infrastructure\Common\Snapshots\Strategies\EventCountSnapshotStrategy(50)
        );

        // Resolves webhook secret via container, not config() in constructor.
        $this->app->when(\App\Http\Controllers\Webhook\PaymentWebhookController::class)
            ->needs('$webhookSecret')
            ->give(function () {
                $secret = config('services.payment.webhook_secret');
                if (! is_string($secret) || empty($secret)) {
                    throw new \RuntimeException('Webhook secret is not configured.');
                }

                return $secret;
            });

        // W-03: Application Service Contracts (DIP Compliance)
        // Replaces direct App\Services imports in Http layer with contracts.
        $this->app->bind(
            \App\Application\Sales\Contracts\OrderStateServiceInterface::class,
            \App\Services\OrderStateService::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\TenantCacheManagerInterface::class,
            \App\Services\TenantCacheManager::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicSchemaRegistryInterface::class,
            \App\Services\DynamicForms\SchemaRegistryService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicFormCompilerInterface::class,
            \App\Services\DynamicForms\FormCompilerService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicRecordPersistenceInterface::class,
            \App\Services\DynamicForms\RecordPersistenceService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicSearchInterface::class,
            \App\Services\DynamicForms\DynamicSearchService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicTemplateRendererInterface::class,
            \App\Services\DynamicForms\TemplateRenderService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicContractCompatibilityInterface::class,
            \App\Services\DynamicForms\DynamicContractCompatibilityService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicSchemaLinterInterface::class,
            \App\Services\DynamicForms\DynamicSchemaLinterService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicCompiledCacheInterface::class,
            \App\Services\DynamicForms\DynamicCompiledCacheService::class
        );
        $this->app->bind(
            \App\Application\DynamicForms\Contracts\DynamicConfigAuditInterface::class,
            \App\Services\DynamicForms\DynamicConfigAuditService::class
        );
        $this->app->singleton(
            \App\Services\DynamicForms\DynamicModuleAdapterRegistry::class,
            static fn ($app) => new \App\Services\DynamicForms\DynamicModuleAdapterRegistry(
                $app->make(\App\Services\DynamicForms\DynamicRuntimeFeatureManager::class)
            )
        );

        // EVENT UPCASTING: Schema Evolution Registry (Singleton)
        $this->app->singleton(
            \App\Application\Shared\Services\EventUpcasterRegistry::class,
            function ($app) {
                $registry = new \App\Application\Shared\Services\EventUpcasterRegistry;

                // Tagged upcasters can be resolved here in the future:
                // foreach ($app->tagged('event.upcasters') as $upcaster) {
                //     $registry->register($upcaster);
                // }

                return $registry;
            }
        );

        // Replaces Service Locator pattern in ValidationMiddleware.
        $this->app->singleton(
            \App\Application\Shared\Contracts\CommandValidatorRegistryInterface::class,
            function ($app) {
                $registry = new \App\Infrastructure\Shared\Middleware\CommandValidatorRegistry;

                // This registry is only for rule-based validators consumed by
                // ValidationMiddleware::rules(). Pipeline decorators that need an
                // $inner handler must be bound through PipelineBuilder instead.
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Identity\Commands\RegisterUserCommand::class,
                    \App\Application\Identity\Validators\RegisterUserValidator::class,
                );
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Tenant\Commands\RegisterTenantCommand::class,
                    \App\Application\Tenant\Validators\RegisterTenantValidator::class,
                );
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Products\Commands\CreateProductCommand::class,
                    \App\Application\Products\Validators\CreateProductValidator::class,
                );
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Products\Commands\UpdateProductCommand::class,
                    \App\Application\Products\Validators\UpdateProductValidator::class,
                );
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Reviews\Commands\SubmitReviewCommand::class,
                    \App\Application\Reviews\Validators\SubmitReviewValidator::class,
                );
                $this->registerRuleBasedCommandValidator(
                    $registry,
                    $app,
                    \App\Application\Sales\Commands\CreateOrderCommand::class,
                    \App\Application\Sales\Validators\CreateOrderValidator::class,
                );

                return $registry;
            }
        );

        // OUTBOX PUBLISHER: Transport Adapter (Log, Redis, etc.)
        $this->app->bind(
            \App\Infrastructure\Common\Outbox\MessagePublisherInterface::class,
            \App\Infrastructure\Common\Outbox\LogMessagePublisher::class
        );

        // DOMAIN EVENT BUS: Decouples Domain Layer from Laravel Event System
        $this->app->bind(DomainEventBus::class, LaravelEventBus::class);

        // SERVICES & CONTRACTS BINDINGS
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

        // NOTIFICATION ABSTRACTION: Decouples Filament from Business Logic
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
            \App\Domain\Directory\Contracts\DirectoryGuideReader::class,
            \App\Infrastructure\Directory\EloquentDirectoryGuideReader::class
        );
        $this->app->bind(
            \App\Application\BusinessAi\Contracts\BusinessAiAuthorizerInterface::class,
            \App\Infrastructure\BusinessAi\TenantBusinessAiAuthorizer::class
        );
        $this->app->bind(
            \App\Application\BusinessAi\Contracts\BusinessKnowledgeIndexerInterface::class,
            \App\Infrastructure\BusinessAi\EloquentBusinessKnowledgeIndexer::class
        );
        $this->app->bind(
            \App\Application\BusinessAi\Contracts\BusinessRagServiceInterface::class,
            \App\Infrastructure\BusinessAi\TenantPrivateRagService::class
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

        // SALES DOMAIN: Order Query Abstraction
        $this->app->bind(
            \App\Domain\Sales\Contracts\OrderReader::class,
            \App\Infrastructure\Sales\EloquentOrderReader::class
        );
        $this->app->bind(
            \App\Domain\Sales\Contracts\ReportReader::class,
            \App\Infrastructure\Sales\EloquentReportReader::class
        );

        // ANALYTICS DOMAIN: Metrics Query Abstraction
        $this->app->bind(
            \App\Domain\Analytics\Contracts\AnalyticsReader::class,
            \App\Infrastructure\Analytics\EloquentAnalyticsReader::class
        );

        // PRODUCT DOMAIN: Inventory Query Abstraction
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
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerConversationRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerConversationRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerTouchpointRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerTouchpointRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\OperationalPlaybookTaskRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentOperationalPlaybookTaskRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CampaignRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCampaignRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerNoteRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerNoteRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerTaskRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerTaskRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerJourneyRunRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerJourneyRunRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerTouchpointEventRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerTouchpointEventRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\InboundWebhookRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentInboundWebhookRepository::class
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
            \App\Domain\Billing\Repositories\PlanRepositoryInterface::class,
            \App\Infrastructure\Billing\Persistence\ConfigPlanRepository::class
        );
        $this->app->bind(
            \App\Domain\Marketing\Contracts\CouponReader::class,
            \App\Infrastructure\Marketing\EloquentCouponReader::class
        );

        // SECURITY DOMAIN
        $this->app->bind(
            \App\Domain\Shared\PasswordHasherInterface::class,
            \App\Infrastructure\Shared\Services\LaravelPasswordHasher::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\OutboxRepositoryInterface::class,
            \App\Infrastructure\Shared\Persistence\EloquentOutboxRepository::class
        );

        // SEARCH & PUBLIC CATALOG
        $this->app->bind(
            \App\Domain\Search\Contracts\GlobalSearchReader::class,
            \App\Infrastructure\Search\EloquentGlobalSearchReader::class
        );
        $this->app->bind(
            \App\Domain\Catalog\Contracts\PublicCatalogReader::class,
            \App\Infrastructure\Catalog\EloquentPublicCatalogReader::class
        );

        // COMMAND BUS: Transactional Outbox Pipeline (P2 Upgrade)
        // Wraps dispatch in DB transaction + persists redacted payload to outbox.
        $this->app->bind(
            \App\Application\Shared\Contracts\CommandBusInterface::class,
            \App\Infrastructure\Bus\TransactionalCommandBus::class
        );

        // REPOSITORY BINDINGS: DIP Compliance for Handlers
        $this->app->bind(
            \App\Domain\Identity\Repositories\UserRepositoryInterface::class,
            \App\Infrastructure\Identity\Persistence\EloquentUserRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentTenantRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerProfileRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerProfileRepository::class
        );
        $this->app->bind(
            \App\Domain\TenantGovernance\Repositories\CustomerConversationRepositoryInterface::class,
            \App\Infrastructure\Repositories\TenantGovernance\EloquentCustomerConversationRepository::class
        );
        $this->app->bind(
            \App\Domain\Billing\Repositories\PaymentRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentPaymentRepository::class
        );
        $this->app->bind(
            \App\Domain\Sales\Repositories\OrderRepositoryInterface::class,
            \App\Infrastructure\Repositories\Sales\EloquentOrderRepository::class
        );

        // PROJECTION BINDINGS: Read-Side Ports for Actions Layer
        $this->app->bind(
            \App\Application\Identity\Contracts\UserReadProjection::class,
            \App\Infrastructure\Identity\Projections\EloquentUserReadProjection::class
        );

        // REVIEWS DOMAIN: Repository Bindings
        $this->app->bind(
            \App\Domain\Reviews\Repositories\ReviewRepositoryInterface::class,
            \App\Infrastructure\Repositories\Reviews\EloquentReviewRepository::class
        );
        $this->app->bind(
            \App\Domain\Product\Repositories\ProductRepositoryInterface::class,
            \App\Infrastructure\Repositories\Products\EloquentProductRepository::class
        );

        // PAYMENT DOMAIN: Repository Bindings
        $this->app->bind(
            \App\Domain\Billing\Repositories\WebhookLogRepositoryInterface::class,
            \App\Infrastructure\Repositories\Payment\EloquentWebhookLogRepository::class
        );

        // IDENTITY DOMAIN: Social Identity Repository
        $this->app->bind(
            \App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface::class,
            \App\Infrastructure\Persistence\EloquentSocialIdentityRepository::class
        );

        // IDENTITY DOMAIN: User Uniqueness Validation (ORM Decoupling)
        $this->app->bind(
            \App\Application\Identity\Contracts\UserUniquenessCheckerInterface::class,
            \App\Infrastructure\Identity\Services\EloquentUserUniquenessChecker::class
        );

        // DOCUMENT STORAGE: Abstraction for file handling in Billing Domain
        $this->app->bind(
            \App\Domain\Billing\Services\DocumentStorageServiceInterface::class,
            \App\Infrastructure\Storage\S3DocumentStorageService::class
        );

        // ANALYTICS DOMAIN: Interaction Repository
        $this->app->bind(
            \App\Domain\Analytics\Repositories\InteractionRepositoryInterface::class,
            \App\Infrastructure\Repositories\Analytics\EloquentInteractionRepository::class
        );

        // SALES DOMAIN: Lead Repository
        $this->app->bind(
            \App\Domain\Sales\Repositories\LeadRepositoryInterface::class,
            \App\Infrastructure\Repositories\Sales\EloquentLeadRepository::class
        );

        // AUTH DOMAIN: OTP Notification and Repository Abstractions
        $this->app->bind(
            \App\Domain\Auth\Contracts\OtpNotifierInterface::class,
            \App\Infrastructure\Auth\Services\OtpNotifierRouter::class
        );

        // OTP Repository - Tenant-Aware Implementation (CANONICAL)
        $this->app->bind(
            \App\Domain\Auth\Repositories\OtpRepositoryInterface::class,
            \App\Infrastructure\Auth\Repositories\EloquentOtpRepository::class
        );

        // OTP CONFIGURATION: Fail-Fast Injection (No Silent Failures)
        // All values MUST be defined in config/auth.php['otp'] or boot will abort.

        // GenerateOtpHandler: OTP Expiry Minutes
        $this->app->when(\App\Application\Auth\Handlers\GenerateOtpHandler::class)
            ->needs('$otpExpiryMinutes')
            ->give(function () {
                $value = config('auth.otp.expiry_minutes');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.expiry_minutes');
                }

                return (int) $value;
            });

        // RateLimitedGenerateOtpHandler: Max Requests Per Hour
        $this->app->when(\App\Application\Auth\Decorators\RateLimitedGenerateOtpHandler::class)
            ->needs('$maxRequestsPerHour')
            ->give(function () {
                $value = config('auth.otp.max_requests_per_hour');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.max_requests_per_hour');
                }

                return (int) $value;
            });

        // GenerateOtpHandler: OTP Length
        $this->app->when(\App\Application\Auth\Handlers\GenerateOtpHandler::class)
            ->needs('$otpLength')
            ->give(function () {
                $value = config('auth.otp.length');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.length');
                }

                return (int) $value;
            });

        // RateLimitedGenerateOtpHandler: Rate Limit Decay Seconds
        $this->app->when(\App\Application\Auth\Decorators\RateLimitedGenerateOtpHandler::class)
            ->needs('$decaySeconds')
            ->give(function () {
                $value = config('auth.otp.rate_limit_decay_seconds');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.rate_limit_decay_seconds');
                }

                return (int) $value;
            });

        // VerifyOtpHandler: Max Verification Attempts
        $this->app->when(\App\Application\Auth\Handlers\VerifyOtpHandler::class)
            ->needs('$maxOtpAttempts')
            ->give(function () {
                $value = config('auth.otp.max_attempts');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.max_attempts');
                }

                return (int) $value;
            });

        // VerifyOtpRateLimitDecorator: Rate Limit Config
        $this->app->when(\App\Application\Auth\Decorators\VerifyOtpRateLimitDecorator::class)
            ->needs('$maxAttempts')
            ->give(fn (): int => (int) config('auth.otp.max_attempts', 5));

        $this->app->when(\App\Application\Auth\Decorators\VerifyOtpRateLimitDecorator::class)
            ->needs('$decaySeconds')
            ->give(fn (): int => 60);

        // AUTH DOMAIN: VERIFY OTP PIPELINE (Standardized)
        $this->app->bind(
            \App\Application\Auth\Handlers\VerifyOtpHandlerInterface::class,
            function ($app) {
                return \App\Infrastructure\Shared\Bus\PipelineBuilder::make(\App\Application\Auth\Handlers\VerifyOtpHandler::class)
                    ->through([
                        // Outermost (Runs First)
                        \App\Application\Auth\Decorators\VerifyOtpRateLimitDecorator::class,
                        \App\Application\Auth\Validators\VerifyOtpCommandValidator::class,
                        \App\Application\Shared\Common\Decorators\AtomicIdempotencyDecorator::class,
                        \App\Application\Shared\Common\Decorators\TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // LoginViaSocialProvider Pipeline
        $this->app->bind(
            \App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface::class,
            function ($app) {
                // IMPORTANT: Core is resolved by PipelineBuilder internally
                return \App\Infrastructure\Shared\Bus\PipelineBuilder::make(\App\Application\Auth\Handlers\LoginViaSocialProviderHandler::class)
                    ->through([
                        \App\Application\Auth\Validators\LoginViaSocialProviderCommandValidator::class,
                        \App\Application\Shared\Common\Decorators\AtomicIdempotencyDecorator::class,
                        \App\Application\Shared\Common\Decorators\TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // DeleteUserHandler Pipeline
        $this->app->bind(
            \App\Application\Auth\Handlers\DeleteUserHandlerInterface::class,
            function ($app) {
                return \App\Infrastructure\Shared\Bus\PipelineBuilder::make(\App\Application\Auth\Handlers\DeleteUserHandler::class)
                    ->through([
                        \App\Application\Auth\Validators\DeleteUserCommandValidator::class,
                        \App\Application\Shared\Common\Decorators\AtomicIdempotencyDecorator::class,
                        \App\Application\Shared\Common\Decorators\TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // UpdateUserPassword Pipeline (Standardized)
        $this->app->bind(
            \App\Application\Auth\Handlers\UpdateUserPasswordHandlerInterface::class,
            function ($app) {
                return \App\Infrastructure\Shared\Bus\PipelineBuilder::make(\App\Application\Auth\Handlers\UpdateUserPasswordHandler::class)
                    ->through([
                        \App\Application\Auth\Validators\UpdateUserPasswordCommandValidator::class,
                        \App\Application\Shared\Common\Decorators\AtomicIdempotencyDecorator::class,
                        \App\Application\Shared\Common\Decorators\TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // GenerateOtp Pipeline (Consolidated & Standardized)
        $this->app->bind(
            \App\Application\Auth\Contracts\GenerateOtpHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                // Resolved via Container to harness contextual bindings defined above
                // PipelineBuilder uses $app->make($coreClass) internally, so we just pass the class name.
                // However, Wait. PipelineBuilder::make() takes a string class name OR an instance?
                // My PipelineBuilder::make takes string.
                // Does it verify contextual bindings? Yes, $container->make($class) respects them.

                return \App\Infrastructure\Shared\Bus\PipelineBuilder::make(\App\Application\Auth\Handlers\GenerateOtpHandler::class)
                    ->through([
                        \App\Application\Auth\Decorators\RateLimitedGenerateOtpHandler::class, // Outer
                        \App\Application\Auth\Decorators\ValidationGenerateOtpHandler::class,  // Gate
                        \App\Application\Auth\Decorators\GenerateOtpIdempotencyDecorator::class,    // Lock
                        \App\Application\Auth\Decorators\TransactionGenerateOtpHandler::class, // ACID Transaction (Strict Typed)
                    ])
                    ->build($app);
            }
        );

        // IdempotentGenerateOtpHandler: Idempotency TTL (Fail-Fast)
        $this->app->when(\App\Application\Auth\Decorators\IdempotentGenerateOtpHandler::class)
            ->needs('$idempotencyTtlSeconds')
            ->give(function () {
                $value = config('auth.otp.idempotency_ttl_seconds');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.idempotency_ttl_seconds');
                }

                return (int) $value;
            });

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

        // BILLING DOMAIN: Subscription Gateway Abstraction
        $this->app->bind(
            \App\Domain\Billing\Gateways\SubscriptionGatewayInterface::class,
            \App\Infrastructure\Billing\Gateways\ManualSubscriptionGateway::class
        );

        // BILLING DOMAIN: Handler Decorator Pipelines (Validation Layer)

        // ReportPayment Pipeline: Validator -> RateLimiter -> Handler (Core)
        $this->app->bind(
            \App\Application\Billing\Handlers\ReportPaymentHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                $core = $app->make(\App\Application\Billing\Handlers\ReportPaymentHandler::class);

                // LAYER 1: Resilience (Fail-Open Rate Limiter)
                $rateLimited = new \App\Application\Billing\Decorators\RateLimitedReportPaymentHandler(
                    $core,
                    $app->make(\App\Application\Shared\Contracts\RateLimiterInterface::class),
                    $app->make(\App\Application\Shared\Contracts\LoggerInterface::class)
                );

                // LAYER 2: Validation Decorator (Outermost)
                // Validator ensures data integrity BEFORE rate limiting logic (to access ClientID/TenantID safely)
                return new \App\Application\Billing\Validators\ReportPaymentCommandValidator(
                    inner: $rateLimited,
                    validatorFactory: $app->make(\App\Application\Shared\Contracts\ValidatorFactoryInterface::class)
                );
            }
        );

        $this->app->bind(
            \App\Application\Billing\Handlers\RequestUpgradeHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                $core = $app->make(\App\Application\Billing\Handlers\RequestUpgradeHandler::class);

                // LAYER 1: Validation Decorator (Outermost)
                return new \App\Application\Billing\Validators\RequestUpgradeCommandValidator(
                    inner: $core,
                    validatorFactory: $app->make(\Illuminate\Contracts\Validation\Factory::class)
                );
            }
        );

        // SHARED: Media Storage Abstraction
        $this->app->bind(
            \App\Domain\Shared\Contracts\MediaStorageServiceInterface::class,
            \App\Infrastructure\Storage\LaravelMediaStorageService::class
        );

        // INVENTORY DOMAIN: Contracts
        $this->app->bind(
            \App\Contracts\InventoryManagerInterface::class,
            \App\Services\InventoryManager::class
        );

        // Binds Application\Shared\Contracts (Ports) to App\Services (Adapters)
        // Required by CreateOrderHandler after concrete service decoupling.
        $this->app->bind(
            \App\Application\Shared\Contracts\CouponValidatorInterface::class,
            \App\Services\CouponValidatorService::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\InventoryManagerInterface::class,
            \App\Services\InventoryManager::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\PriceCalculatorInterface::class,
            \App\Services\PriceCalculatorService::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\IdempotencyServiceInterface::class,
            function ($app) {
                $hasRedisExtension = extension_loaded('redis');
                $isTestRuntime = $app->environment(['testing']) || $app->runningUnitTests();

                if ($isTestRuntime || ! $hasRedisExtension) {
                    return $app->make(\App\Infrastructure\Common\Idempotency\CacheIdempotencyService::class);
                }

                return $app->make(\App\Infrastructure\Common\Idempotency\RedisIdempotencyService::class);
            }
        );

        // ORDERS DOMAIN: Receipt PDF Generator
        $this->app->bind(
            \App\Domain\Orders\Contracts\ReceiptGeneratorInterface::class,
            \App\Infrastructure\Reporting\DomPdfReceiptGenerator::class
        );

        // SECURITY: Rate Limiting Abstraction
        $this->app->bind(
            \App\Domain\Shared\Contracts\RateLimitServiceInterface::class,
            \App\Infrastructure\Security\LaravelRateLimitService::class
        );

        // SECURITY: Metadata Sanitizer Abstraction (DIP Compliance for Actions)
        $this->app->bind(
            \App\Application\Shared\Contracts\MetadataSanitizerInterface::class,
            \App\Infrastructure\Security\MetadataSanitizer::class
        );

        // TRANSACTIONAL OUTBOX: Event Dispatcher with At-Least-Once Delivery
        $this->app->singleton(
            \App\Infrastructure\Bus\TransactionalEventDispatcher::class,
            \App\Infrastructure\Bus\TransactionalEventDispatcher::class
        );

        // RATE LIMITING: Tenant-Aware Protection for Actions Layer
        $this->app->bind(
            \App\Application\Shared\Contracts\TenantRateLimiterInterface::class,
            \App\Infrastructure\Security\CacheTenantRateLimiter::class
        );

        // INFRASTRUCTURE: Fundamentals (Time, Entropy, Transactions, Outbox)
        $this->app->bind(
            \App\Application\Shared\Contracts\OutboxRepositoryInterface::class,
            \App\Infrastructure\Shared\Persistence\EloquentOutboxRepository::class
        );
        $this->app->bind(
            \App\Domain\Shared\Contracts\ClockInterface::class,
            \App\Infrastructure\Shared\Time\SystemClock::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\EntropyGeneratorInterface::class,
            \App\Infrastructure\Shared\Security\NativeEntropyGenerator::class
        );
        $this->app->bind(
            \App\Domain\Common\Contracts\EventStoreRepositoryInterface::class,
            \App\Infrastructure\Common\EventStore\DatabaseEventStore::class
        );
        $this->app->bind(
            \App\Domain\Auth\Services\OtpHasherInterface::class,
            \App\Infrastructure\Auth\Services\HmacOtpHasher::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\TransactionManagerInterface::class,
            \App\Infrastructure\Shared\Persistence\DatabaseTransactionManager::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\IdGeneratorInterface::class,
            \App\Infrastructure\Adapters\FrameworkIdGenerator::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\ValidationServiceInterface::class,
            \App\Infrastructure\Shared\Services\LaravelValidationService::class
        );

        $this->app->bind(
            \App\Domain\Shared\Contracts\UuidGeneratorInterface::class,
            \App\Infrastructure\Identity\RamseyUuidGenerator::class
        );

        // TENANT GOVERNANCE: Plan Limits Abstraction (A5 - Settings Isolation)
        // Binds PlanLimitsInterface to Infrastructure adapter wrapping GeneralSettings
        $this->app->bind(
            \App\Domain\TenantGovernance\Contracts\PlanLimitsInterface::class,
            \App\Infrastructure\TenantGovernance\Settings\SettingsPlanLimits::class
        );

        $this->app->bind(
            \App\Domain\Shared\Contracts\EventDispatcherInterface::class,
            \App\Infrastructure\Bus\TransactionalEventDispatcher::class
        );

        // LOGGING: PII Scrubber Abstraction (Application Layer Purity)
        $this->app->bind(
            \App\Application\Shared\Contracts\DataScrubberInterface::class,
            \App\Infrastructure\Logging\SensitiveDataScrubber::class
        );

        // INFRASTRUCTURE ABSTRACTIONS: DIP Compliance (Application Layer Purity)
        $this->app->bind(
            \App\Application\Shared\Contracts\PresignedUrlGeneratorInterface::class,
            \App\Infrastructure\Services\S3PresignerService::class
        );

        $this->app->singleton(
            \App\Application\Shared\Contracts\TraceContextInterface::class,
            \App\Infrastructure\Shared\Telemetry\TraceContext::class
        );

        $this->app->singleton(
            \App\Application\Shared\Contracts\IdempotencyContextInterface::class,
            \App\Infrastructure\Shared\Context\IdempotencyContext::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\ResilientRateLimiterInterface::class,
            \App\Infrastructure\Security\ResilientRateLimiter::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\SlugGeneratorInterface::class,
            \App\Infrastructure\Services\LaravelSlugGenerator::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\FileUrlResolverInterface::class,
            \App\Infrastructure\Storage\LaravelFileUrlResolver::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\ValidatorFactoryInterface::class,
            \App\Infrastructure\Shared\Services\LaravelValidatorFactory::class
        );

        // INFRASTRUCTURE: Resilience & Observability
        $this->app->scoped(
            \App\Infrastructure\Shared\Telemetry\TraceContext::class,
            \App\Infrastructure\Shared\Telemetry\TraceContext::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\MetricsCollectorInterface::class,
            \App\Infrastructure\Monitoring\DatadogMetricsAdapter::class
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\CircuitBreakerInterface::class,
            function ($app) {
                $hasRedisExtension = extension_loaded('redis');
                $isTestRuntime = $app->environment(['testing']) || $app->runningUnitTests();

                if ($isTestRuntime || ! $hasRedisExtension) {
                    return $app->make(\App\Infrastructure\Resilience\NullCircuitBreaker::class);
                }

                return new \App\Infrastructure\Resilience\RedisCircuitBreaker(
                    redis: $app['redis']->connection(), // Explicit Connection Resolution
                    metrics: $app->make(\App\Application\Shared\Contracts\MetricsCollectorInterface::class)
                );
            }
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\AtomicIdempotencyInterface::class,
            \App\Infrastructure\Common\Idempotency\AtomicIdempotencyService::class
        );

        // DOMAIN EVENTS: Infrastructure Bridge
        $this->app->bind(
            \App\Contracts\Events\DomainEventBus::class,
            \App\Infrastructure\Events\LaravelEventBus::class
        );

        // IDEMPOTENCY: Titanium Stability (AUTH & IDENTITY)

        // 1. CONTEXT PROVIDER (Polymorphic Identity)
        $this->app->bind(
            \App\Application\Shared\Contracts\ClientContextProviderInterface::class,
            function ($app) {
                return $app->runningInConsole()
                    ? new \App\Infrastructure\Shared\ConsoleContextProvider
                    : new \App\Infrastructure\Shared\RequestContextProvider($app['request']);
            }
        );

        // 3. IDENTITY DOMAIN DECORATOR STACK (The Onion)
        // Order: Security -> Observability -> Consistency -> Core
        $this->app->bind(
            \App\Application\Identity\Handlers\RegisterUserHandlerInterface::class,
            function ($app) {
                // CORE: The Business Logic (Pure)
                $core = $app->make(\App\Application\Identity\Handlers\RegisterUserHandler::class);

                // LAYER 1: Idempotency (The Guard) - Protects Core from duplicates
                // Uses the container-resolved IdempotencyService
                $idempotent = new \App\Application\Identity\Decorators\IdempotentRegisterUserHandler(
                    inner: $core,
                    service: $app->make(\App\Application\Shared\Contracts\AtomicIdempotencyInterface::class)
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
                    // Use Interface
                    rateLimiter: $app->make(\App\Application\Shared\Contracts\RateLimiterInterface::class),
                    contextProvider: $app->make(\App\Application\Shared\Contracts\ClientContextProviderInterface::class)
                );
            }
        );

        $this->app->bind(
            \App\Application\Shared\Contracts\RateLimiterInterface::class,
            \App\Infrastructure\Common\RateLimiting\LaravelRateLimiterAdapter::class
        );

        // 4. SALES DOMAIN: Idempotency Protection
        $this->app->bind(
            \App\Application\Sales\Handlers\CreateOrderHandlerInterface::class,
            function ($app) {
                $core = $app->make(\App\Application\Sales\Handlers\CreateOrderHandler::class);

                return new \App\Application\Sales\Decorators\IdempotentCreateOrderHandler(
                    inner: $core,
                    service: $app->make(\App\Application\Shared\Contracts\IdempotencyServiceInterface::class)
                );
            }
        );

        // 5. TENANCY DOMAIN: Idempotency Protection
        $this->app->bind(
            \App\Application\Tenant\Handlers\RegisterTenantHandlerInterface::class,
            function ($app) {
                $core = $app->make(\App\Application\Tenant\Handlers\RegisterTenantHandler::class);

                return new \App\Application\Tenant\Decorators\IdempotentRegisterTenantHandler(
                    inner: $core,
                    service: $app->make(\App\Application\Shared\Contracts\IdempotencyServiceInterface::class)
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // COMMAND BUS: Schema Evolution Decorator (Upcasting Pipeline)
        // Intercepts commands BEFORE TransactionalCommandBus to transform
        // payloads from older schemaVersion to current shape.
        $this->app->extend(
            \App\Application\Shared\Contracts\CommandBusInterface::class,
            function ($commandBus, $app) {
                return new \App\Application\Shared\Decorators\UpcastingCommandBus(
                    $commandBus,
                    $app->tagged('command_upcasters')
                );
            }
        );

        // LOCALIZATION: Sync Carbon locale with Laravel app.locale
        // Carbon does NOT auto-sync with config('app.locale'). This ensures all
        // date formatting (diffForHumans, translatedFormat) respects the locale.
        \Carbon\Carbon::setLocale(config('app.locale'));

        // POLYMORPHIC INTEGRITY: Morph Map for Domain Migration
        // CRITICAL: This mapping MUST be registered BEFORE moving model files.
        // It decouples the database's model_type column from the PHP namespace.
        // Without this, activity_log, media, comments and other polymorphic
        // tables would have orphaned records pointing to non-existent classes.
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'tenant' => \App\Models\Tenant::class,
            'product' => \App\Models\Product::class,
            'order' => \App\Models\Order::class,
            'coupon' => \App\Models\Coupon::class,
        ]);

        // DOMAIN MIGRATION: Model Aliases for Gradual Transition
        // 80+ files still reference App\Models\User and App\Models\Tenant.
        // These aliases maintain backward compatibility during gradual transition.
        // REMOVE AFTER: 2026-03-05 (60 days post-migration)
        $this->registerModelAliases();

        // DEPLOYMENT SAFETY: Class Aliases for Migrated Jobs
        // Jobs in Redis queue may reference old App\Jobs\* namespace.
        // These aliases redirect to new App\Domain\*\Jobs\* locations.
        // REMOVE AFTER: 2026-02-05 (30 days post-migration)
        $this->registerJobAliases();

        // DATA GOVERNANCE: Observers & Global Policies
        // We register policies here explicitly to avoid auto-discovery overhead
        // and enforce architectural boundaries.

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
        Gate::policy(\App\Models\Dynamic\EntityDefinition::class, \App\Policies\EntityDefinitionPolicy::class);
        Gate::policy(\App\Models\Dynamic\FieldDefinition::class, \App\Policies\FieldDefinitionPolicy::class);
        Gate::policy(\App\Models\Dynamic\LayoutDefinition::class, \App\Policies\LayoutDefinitionPolicy::class);
        Gate::policy(\App\Models\Dynamic\DynamicPrintTemplate::class, \App\Policies\DynamicPrintTemplatePolicy::class);
        Gate::policy(\App\Models\Dynamic\DynamicRecord::class, \App\Policies\DynamicRecordPolicy::class);

        // Observers
        Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Tenant::observe(\App\Observers\TenantObserver::class);
        Order::observe(\App\Observers\OrderCustomerProfileObserver::class);
        Appointment::observe(\App\Observers\AppointmentCustomerProfileObserver::class);
        Lead::observe(\App\Observers\LeadCustomerProfileObserver::class);

        // SECURITY: Rate Limiters
        \Illuminate\Support\Facades\RateLimiter::for('analytics-protection', function (\Illuminate\Http\Request $request) {
            // Composite Key: IP + User Agent + Target Tenant
            // Prevents botnet distributed attacks against a specific tenant.
            $key = $request->ip().'|'.$request->userAgent().'|'.$request->input('tenant_id');

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($key);
        });

        // TENANT-AWARE RATE LIMITING (Contextual Throttling)
        \Illuminate\Support\Facades\RateLimiter::for('tenant-aware', function (\Illuminate\Http\Request $request) {
            // Resolve Tenant ID securely from Context if available
            // If explicit 'tenant_id' input is missing, try to resolve from route or attributes
            // Fallback to IP if purely anonymous without context

            /** @var \App\Domain\Shared\Contracts\TenantContext $context */
            $context = app(\App\Domain\Shared\Contracts\TenantContext::class);
            try {
                $tenantId = (string) $context->getTenantId();
            } catch (\RuntimeException) {
                $tenantId = (string) $request->input('tenant_id', '');
            }

            $key = $tenantId
                ? "tenant_id:{$tenantId}|ip:".$request->ip()
                : 'ip:'.$request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($key); // Increased to 120 as per prompt example
        });

        // BUS CONFIGURATION: Middleware & Mappings
        \Illuminate\Support\Facades\Bus::pipeThrough([
            \App\Infrastructure\Bus\Middleware\TransactionalMiddleware::class,
        ]);

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Sales\Events\CartAbandoned::class,
            \App\Infrastructure\Listeners\Sales\SendAbandonedCartOfferListener::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Booking\Events\AppointmentCancelled::class,
            \App\Infrastructure\Listeners\Booking\CheckWaitlistOnCancellation::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\CatalogPublishedChanged::class,
            \App\Listeners\Directory\SyncPublishedCatalogEmbeddings::class
        );

        \Illuminate\Support\Facades\Bus::map([
            \App\Application\Identity\Commands\RegisterUserCommand::class => \App\Application\Identity\Handlers\RegisterUserHandlerInterface::class,
            \App\Application\Sales\Commands\CreateOrderCommand::class => \App\Application\Sales\Handlers\CreateOrderHandler::class,
            \App\Application\Tenant\Commands\RegisterTenantCommand::class => \App\Application\Tenant\Handlers\RegisterTenantHandler::class,
            \App\Application\Tenant\Commands\CancelAppointmentCommand::class => \App\Application\Tenant\Handlers\CancelAppointmentHandler::class,
            \App\Application\Tenant\Commands\CreateAppointmentCommand::class => \App\Application\Tenant\Handlers\CreateAppointmentHandler::class,
            // \App\Application\Identity\Commands\RegisterUserCommand::class => \App\Application\Identity\Handlers\RegisterUserHandler::class, (Replaced by Decorator Stack)
            \App\Application\Billing\Commands\ReportPaymentCommand::class => \App\Application\Billing\Handlers\ReportPaymentHandlerInterface::class,
            \App\Application\Reviews\Commands\SubmitReviewCommand::class => \App\Application\Reviews\Handlers\SubmitReviewHandler::class,
            \App\Application\Products\Commands\CreateProductCommand::class => \App\Application\Products\Handlers\CreateProductHandler::class,
            \App\Application\Products\Commands\UpdateProductCommand::class => \App\Application\Products\Handlers\UpdateProductHandler::class,
            \App\Application\Products\Commands\DeleteProductCommand::class => \App\Application\Products\Handlers\DeleteProductHandler::class,
            \App\Application\Products\Commands\ToggleProductVisibilityCommand::class => \App\Application\Products\Handlers\ToggleProductVisibilityHandler::class,
            \App\Application\Sales\Commands\CancelOrderCommand::class => \App\Application\Sales\Handlers\CancelOrderHandler::class,
            \App\Application\Billing\Commands\RequestUpgradeCommand::class => \App\Application\Billing\Handlers\RequestUpgradeHandlerInterface::class,
            \App\Application\Auth\Commands\LoginViaSocialProviderCommand::class => \App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface::class,
            \App\Application\Auth\Commands\UpdateUserPasswordCommand::class => \App\Application\Auth\Handlers\UpdateUserPasswordHandlerInterface::class,
            \App\Application\Identity\Commands\UpdateUserProfileCommand::class => \App\Application\Identity\Handlers\UpdateUserProfileHandler::class,
            \App\Application\Auth\Commands\DeleteUserCommand::class => \App\Application\Auth\Handlers\DeleteUserHandlerInterface::class,
            \App\Application\Auth\Commands\GenerateOtpCommand::class => \App\Application\Auth\Contracts\GenerateOtpHandlerInterface::class,
            \App\Application\Auth\Commands\VerifyOtpCommand::class => \App\Application\Auth\Handlers\VerifyOtpHandlerInterface::class,
            \App\Application\Tenant\Commands\UpdateTenantScheduleCommand::class => \App\Application\Tenant\Handlers\UpdateTenantScheduleHandler::class,
            \App\Application\Tenant\Commands\UpdateLandingPageCommand::class => \App\Application\Tenant\Handlers\UpdateLandingPageHandler::class,
            \App\Application\Tenant\Commands\UpdateTenantBrandingCommand::class => \App\Application\Tenant\Handlers\UpdateTenantBrandingHandler::class,
            \App\Application\Interactions\Commands\RecordInteractionCommand::class => \App\Application\Interactions\Handlers\RecordInteractionHandler::class,
            \App\Application\Identity\Commands\ReactivateUserCommand::class => \App\Application\Identity\Handlers\ReactivateUserHandler::class,
            \App\Application\Auth\Commands\ResetUserPasswordCommand::class => \App\Application\Auth\Handlers\ResetUserPasswordHandler::class,
        ]);

        // EVENT LISTENERS: Eventual Consistency Handlers
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Reviews\Events\ReviewCreated::class,
            \App\Application\Reviews\Listeners\UpdateProductRating::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Sales\Events\OrderCancelled::class,
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

    private function registerRuleBasedCommandValidator(
        \App\Infrastructure\Shared\Middleware\CommandValidatorRegistry $registry,
        \Illuminate\Contracts\Container\Container $app,
        string $commandClass,
        string $validatorClass,
    ): void {
        if (! class_exists($commandClass) || ! class_exists($validatorClass) || ! method_exists($validatorClass, 'rules')) {
            return;
        }

        $registry->register($commandClass, $app->make($validatorClass));
    }
}
