<?php

declare(strict_types=1);

namespace App\Providers;

// Models
use App\Actions\Authentication\ResetPasswordAction;
use App\Application\Ai\Contracts\AiGateway;
use App\Application\Ai\Contracts\AiProviderPool;
use App\Application\Ai\Contracts\StructuredOutputValidator;
use App\Application\Ai\Knowledge\EmbeddingProvider;
use App\Application\Ai\Knowledge\KnowledgeRetriever;
use App\Application\Ai\Knowledge\SemanticKnowledgeRetriever;
use App\Application\Ai\PrivateAiTenantAccessReaderInterface;
use App\Application\Analytics\Listeners\ProjectCommercialOutcomeFromOrderEvent;
// Policies
use App\Application\Auth\Commands\DeleteUserCommand;
use App\Application\Auth\Commands\GenerateOtpCommand;
use App\Application\Auth\Commands\LoginViaSocialProviderCommand;
use App\Application\Auth\Commands\UpdateUserPasswordCommand;
use App\Application\Auth\Commands\UpdateUserProfileCommand;
use App\Application\Auth\Commands\VerifyOtpCommand;
use App\Application\Auth\Contracts\GenerateOtpHandlerInterface;
use App\Application\Auth\Decorators\IdempotentGenerateOtpHandler;
use App\Application\Auth\Decorators\RateLimitedGenerateOtpHandler;
use App\Application\Auth\Decorators\TransactionGenerateOtpHandler;
use App\Application\Auth\Decorators\TransactionVerifyOtpHandler;
// Event Bus Infrastructure
use App\Application\Auth\Decorators\ValidationGenerateOtpHandler;
use App\Application\Auth\Decorators\VerifyOtpRateLimitDecorator;
use App\Application\Auth\Handlers\DeleteUserHandler;
use App\Application\Auth\Handlers\DeleteUserHandlerInterface;
use App\Application\Auth\Handlers\GenerateOtpHandler;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandler;
use App\Application\Auth\Handlers\LoginViaSocialProviderHandlerInterface;
use App\Application\Auth\Handlers\UpdateUserPasswordHandler;
use App\Application\Auth\Handlers\UpdateUserPasswordHandlerInterface;
use App\Application\Auth\Handlers\UpdateUserProfileHandler;
use App\Application\Auth\Handlers\VerifyOtpHandler;
use App\Application\Auth\Handlers\VerifyOtpHandlerInterface;
use App\Application\Auth\Validators\DeleteUserCommandValidator;
use App\Application\Auth\Validators\LoginViaSocialProviderCommandValidator;
use App\Application\Auth\Validators\UpdateUserPasswordCommandValidator;
use App\Application\Auth\Validators\VerifyOtpCommandValidator;
use App\Application\Availability\AvailabilityQuery;
use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Contracts\PaymentWebhookSettlementInterface;
use App\Application\Billing\Contracts\SubscriptionIntentStoreInterface;
use App\Application\Billing\Handlers\ReportPaymentHandler;
use App\Application\Billing\Handlers\ReportPaymentHandlerInterface;
use App\Application\Billing\Handlers\RequestUpgradeHandler;
use App\Application\Billing\Handlers\RequestUpgradeHandlerInterface;
use App\Application\Billing\Validators\ReportPaymentCommandValidator;
use App\Application\Billing\Validators\RequestUpgradeCommandValidator;
use App\Application\Common\Contracts\AtomicIdempotencyInterface;
use App\Application\Common\Contracts\CircuitBreakerInterface;
use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Common\Contracts\MetricsCollectorInterface;
use App\Application\Common\Contracts\RateLimiterInterface;
use App\Application\Common\Decorators\AtomicIdempotencyDecorator;
use App\Application\Common\Decorators\TransactionDecorator;
use App\Application\Common\Interfaces\ClientContextProviderInterface;
use App\Application\Identity\Commands\RegisterUserCommand;
use App\Application\Identity\Decorators\IdempotentRegisterUserHandler;
use App\Application\Identity\Decorators\RateLimitedRegisterUserHandler;
use App\Application\Identity\Decorators\TelemetryRegisterUserHandler;
use App\Application\Identity\Handlers\RegisterUserHandler;
use App\Application\Identity\Handlers\RegisterUserHandlerInterface;
use App\Application\Interactions\Commands\RecordInteractionCommand;
use App\Application\Interactions\Contracts\LeadFollowUpPlaybookInterface;
use App\Application\Interactions\Handlers\RecordInteractionHandler;
use App\Application\Payments\Commands\HandlePaymentWebhookCommand;
use App\Application\Payments\Handlers\HandlePaymentWebhookHandler;
use App\Application\Operations\OperationalCockpit;
use App\Application\Operations\OperationalKnowledgeHealth;
use App\Application\Products\ProductImageStorageInterface;
use App\Application\Reviews\Commands\SubmitReviewCommand;
use App\Application\Reviews\Handlers\SubmitReviewHandler;
use App\Application\Reviews\Listeners\UpdateProductRating;
use App\Application\Sales\Commands\CancelOrderCommand;
use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Decorators\IdempotentCreateOrderHandler;
use App\Application\Sales\Handlers\CancelOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandler;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\Sales\Listeners\RestoreStockOnCancellation;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\DataScrubberInterface;
use App\Application\Shared\Contracts\MetadataSanitizerInterface;
use App\Application\Shared\Contracts\TenantRateLimiterInterface;
use App\Application\Shared\Contracts\TraceContextInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Application\Shared\Contracts\ValidationServiceInterface;
use App\Application\Shared\Interfaces\EntropyGeneratorInterface;
use App\Application\Tenant\Commands\RegisterTenantCommand;
use App\Application\Tenant\Commands\UpdateLandingPageCommand;
use App\Application\Tenant\Commands\UpdateTenantBrandingCommand;
use App\Application\Tenant\Commands\UpdateTenantScheduleCommand;
use App\Application\Tenant\Decorators\IdempotentRegisterTenantHandler;
use App\Application\Tenant\Handlers\RegisterTenantHandler;
use App\Application\Tenant\Handlers\RegisterTenantHandlerInterface;
use App\Application\Tenant\Handlers\UpdateLandingPageHandler;
use App\Application\Tenant\Handlers\UpdateTenantBrandingHandler;
use App\Application\Tenant\Handlers\UpdateTenantScheduleHandler;
use App\Application\TenantGovernance\Crm\CrmDefaultPipelineGatewayInterface;
use App\Application\TenantGovernance\Crm\CrmPipelineSummaryGatewayInterface;
use App\Contracts\DataExportInterface;
use App\Contracts\Events\DomainEventBus;
use App\Contracts\LoggerInterface;
use App\Contracts\ReportGenerationInterface;
use App\Domain\Analytics\Contracts\AnalyticsReader;
use App\Domain\Analytics\Repositories\InteractionRepositoryInterface;
use App\Domain\Api\Contracts\NotificationRepository;
use App\Domain\Auth\Contracts\OtpNotifierInterface;
use App\Domain\Auth\Repositories\OtpRepositoryInterface;
use App\Domain\Auth\Services\OtpHasherInterface;
use App\Domain\Billing\Contracts\BillingReader;
use App\Domain\Billing\Gateways\SubscriptionCheckoutGatewayInterface;
use App\Domain\Billing\Gateways\SubscriptionGatewayInterface;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Billing\Repositories\PlanRepositoryInterface;
use App\Domain\Billing\Repositories\SubscriptionIntentRepositoryInterface;
use App\Domain\Billing\Services\DocumentStorageServiceInterface;
use App\Domain\Catalog\Contracts\ProductCatalogReader;
use App\Domain\Catalog\Contracts\PublicCatalogReader;
use App\Domain\Common\Contracts\EventStoreRepositoryInterface;
use App\Domain\Contracts\AdminNotifier;
use App\Domain\Contracts\SearchRepository;
use App\Domain\Directory\Contracts\DirectoryReader;
use App\Domain\Identity\Repositories\SocialIdentityRepositoryInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Marketing\Contracts\CouponReader;
use App\Domain\Orders\Contracts\ReceiptGeneratorInterface;
use App\Domain\Payment\Repositories\WebhookLogRepositoryInterface;
use App\Domain\Product\Contracts\ProductReader;
use App\Domain\Product\Repositories\ProductBlurhashWriter;
use App\Domain\Product\Repositories\ProductRatingMetricsWriter;
use App\Domain\Public\Contracts\BuyerRepository;
use App\Domain\Public\Contracts\LandingPageRepository;
use App\Domain\Public\Contracts\TenantStorefrontRepository;
use App\Domain\Reviews\Events\ReviewCreated;
use App\Domain\Reviews\Repositories\ReviewRepositoryInterface;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Sales\Contracts\ReportReader;
use App\Domain\Sales\Repositories\LeadRepositoryInterface;
use App\Domain\Search\Contracts\GlobalSearchReader;
use App\Domain\Shared\Contracts\AdminNotifierInterface;
use App\Domain\Shared\Contracts\ClockInterface;
use App\Domain\Shared\Contracts\EventDispatcherInterface;
use App\Domain\Shared\Contracts\FailureNotifierInterface;
use App\Domain\Shared\Contracts\MediaStorageServiceInterface;
use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Shared\Contracts\TenantReadRepository;
use App\Domain\Shared\Contracts\UuidGeneratorInterface;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\Shared\PasswordHasherInterface;
use App\Domain\Tenant\Contracts\BillingRepository;
use App\Domain\Tenant\Contracts\CouponRepository;
use App\Domain\Tenant\Contracts\DashboardMetricsRepository;
use App\Domain\Tenant\Contracts\OnboardingReader;
use App\Domain\Tenant\Contracts\OrderRepository;
use App\Domain\Tenant\Contracts\TenantProductRepository;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Events\Order\OrderCancelled;
use App\Events\Order\OrderDelivered;
use App\Events\Order\OrderPaid;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Infrastructure\AI\ConfiguredAiProviderPool;
use App\Infrastructure\AI\DisabledAiProvider;
use App\Infrastructure\AI\EloquentPrivateAiTenantAccessReader;
use App\Infrastructure\AI\Knowledge\OpenAiEmbeddingProvider;
use App\Infrastructure\AI\LaravelStructuredOutputValidator;
use App\Infrastructure\AI\OpenAiStructuredTextProvider;
use App\Infrastructure\AI\ResilientAiGateway;
use App\Infrastructure\Analytics\EloquentAnalyticsReader;
use App\Infrastructure\Api\EloquentNotificationRepository;
use App\Infrastructure\Auth\Repositories\EloquentOtpRepository;
use App\Infrastructure\Auth\Services\HmacOtpHasher;
use App\Infrastructure\Availability\EloquentAvailabilityQuery;
use App\Infrastructure\Billing\EloquentBillingReader;
use App\Infrastructure\Billing\EloquentPaymentWebhookSettlement;
use App\Infrastructure\Billing\EloquentSubscriptionIntentStore;
use App\Infrastructure\Billing\Gateways\ManualSubscriptionCheckoutGateway;
use App\Infrastructure\Billing\Gateways\ManualSubscriptionGateway;
use App\Infrastructure\Billing\Persistence\ConfigPlanRepository;
use App\Infrastructure\Billing\Persistence\EloquentSubscriptionIntentRepository;
use App\Infrastructure\Bus\IlluminateCommandBus;
use App\Infrastructure\Bus\Middleware\TransactionalMiddleware;
use App\Infrastructure\Bus\TransactionalEventDispatcher;
use App\Infrastructure\Catalog\CachedProductReader;
use App\Infrastructure\Catalog\EloquentPublicCatalogReader;
use App\Infrastructure\Common\EventStore\DatabaseEventStore;
use App\Infrastructure\Common\Idempotency\AtomicIdempotencyService;
use App\Infrastructure\Common\Outbox\LaravelEventMessagePublisher;
use App\Infrastructure\Common\Outbox\MessagePublisherInterface;
use App\Infrastructure\Common\RateLimiting\LaravelRateLimiterAdapter;
use App\Infrastructure\Crm\EloquentCrmDefaultPipelineGateway;
use App\Infrastructure\Crm\EloquentCrmPipelineSummaryGateway;
use App\Infrastructure\Directory\EloquentDirectoryReader;
use App\Infrastructure\Events\LaravelEventBus;
use App\Infrastructure\Identity\Persistence\EloquentUserRepository;
use App\Infrastructure\Identity\RamseyUuidGenerator;
use App\Infrastructure\Interactions\EloquentLeadFollowUpPlaybook;
use App\Infrastructure\Jobs\AI\EnhanceTenantProfileJob;
use App\Infrastructure\Jobs\AI\GenerateBusinessProfile;
use App\Infrastructure\Jobs\Analytics\RecordInteraction;
use App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob;
use App\Infrastructure\Jobs\Analytics\SyncTenantAnalytics;
use App\Infrastructure\Jobs\Exports\GenerateSalesExport;
use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use App\Infrastructure\Jobs\Governance\ReleaseStaleOrders;
use App\Infrastructure\Jobs\Marketing\SendCampaignEmail;
use App\Infrastructure\Jobs\Media\GenerateProductBlurhash;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Marketing\EloquentCouponReader;
use App\Infrastructure\Monitoring\DatadogMetricsAdapter;
use App\Infrastructure\Notifications\EmailOtpNotifier;
use App\Infrastructure\Notifications\FilamentAdminNotifier;
use App\Infrastructure\Notifications\LogFailureNotifier;
use App\Infrastructure\Payments\Stripe\StripeSubscriptionCheckoutGateway;
use App\Infrastructure\Persistence\EloquentPaymentRepository;
use App\Infrastructure\Persistence\EloquentSocialIdentityRepository;
use App\Infrastructure\Product\EloquentProductReader;
use App\Infrastructure\Public\CachedTenantStorefrontRepository;
use App\Infrastructure\Public\EloquentBuyerRepository;
use App\Infrastructure\Public\EloquentLandingPageRepository;
use App\Infrastructure\Public\EloquentTenantStorefrontRepository;
use App\Infrastructure\Reporting\DomPdfReceiptGenerator;
use App\Infrastructure\Repositories\Analytics\EloquentInteractionRepository;
use App\Infrastructure\Repositories\Payment\EloquentWebhookLogRepository;
use App\Infrastructure\Repositories\Products\EloquentProductBlurhashWriter;
use App\Infrastructure\Repositories\Products\EloquentProductRatingMetricsWriter;
use App\Infrastructure\Repositories\Reviews\EloquentReviewRepository;
use App\Infrastructure\Repositories\Sales\EloquentLeadRepository;
use App\Infrastructure\Repositories\TenantGovernance\EloquentTenantRepository;
use App\Infrastructure\Resilience\RedisCircuitBreaker;
use App\Infrastructure\Sales\EloquentOrderReader;
use App\Infrastructure\Sales\EloquentReportReader;
use App\Infrastructure\Search\EloquentGlobalSearchReader;
use App\Infrastructure\Search\EloquentSearchRepository;
use App\Infrastructure\Operations\EloquentOperationalCockpit;
use App\Infrastructure\Operations\EloquentOperationalKnowledgeHealth;
use App\Infrastructure\Security\CacheTenantRateLimiter;
use App\Infrastructure\Security\LaravelRateLimitService;
use App\Infrastructure\Security\MetadataSanitizer;
use App\Infrastructure\Shared\Bus\PipelineBuilder;
use App\Infrastructure\Shared\ConsoleContextProvider;
use App\Infrastructure\Shared\EloquentTenantReadRepository;
use App\Infrastructure\Shared\Persistence\DatabaseTransactionManager;
use App\Infrastructure\Shared\Persistence\Decorators\SchemaValidatingOutboxRepository;
use App\Infrastructure\Shared\Persistence\EloquentOutboxRepository;
use App\Infrastructure\Shared\RequestContextProvider;
use App\Infrastructure\Shared\RequestTenantContext;
use App\Infrastructure\Shared\Security\NativeEntropyGenerator;
use App\Infrastructure\Shared\Services\LaravelPasswordHasher;
use App\Infrastructure\Shared\Services\LaravelValidationService;
use App\Infrastructure\Shared\Telemetry\TraceContext;
use App\Infrastructure\Shared\Time\SystemClock;
use App\Infrastructure\Storage\LaravelMediaStorageService;
use App\Infrastructure\Storage\NormalizedProductImageStorage;
use App\Infrastructure\Storage\S3DocumentStorageService;
use App\Infrastructure\Tenant\CachedOnboardingReader;
use App\Infrastructure\Tenant\EloquentBillingRepository;
use App\Infrastructure\Tenant\EloquentCouponRepository;
use App\Infrastructure\Tenant\EloquentDashboardMetricsRepository;
use App\Infrastructure\Tenant\EloquentOrderRepository;
use App\Infrastructure\Tenant\EloquentTenantProductRepository;
use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\CrmDeal;
use App\Models\CustomerNote;
use App\Models\CustomerTask;
use App\Models\Lead;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceProvider as ServiceProviderModel;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\CommercialOutcomeObserver;
use App\Observers\KnowledgeIndexObserver;
use App\Observers\ProductObserver;
use App\Observers\TenantObserver;
use App\Policies\CampaignPolicy;
use App\Policies\CouponPolicy;
use App\Policies\LeadPolicy;
use App\Policies\MenuSectionPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Services\ExcelExportService;
use App\Services\LaravelLoggerAdapter;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OperationalKnowledgeHealth::class,
            EloquentOperationalKnowledgeHealth::class,
        );
        $this->app->bind(
            OperationalCockpit::class,
            EloquentOperationalCockpit::class,
        );

        $this->app->bind(
            AvailabilityQuery::class,
            EloquentAvailabilityQuery::class,
        );

        // ═══════════════════════════════════════════════════════════════════════
        // TITANIUM EVENT STORE: The One True Store (DB + Outbox)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            EventStoreRepositoryInterface::class,
            DatabaseEventStore::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // OUTBOX PUBLISHER: Transport Adapter (Log, Redis, etc.)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            MessagePublisherInterface::class,
            LaravelEventMessagePublisher::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // DOMAIN EVENT BUS: Decouples Domain Layer from Laravel Event System
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(DomainEventBus::class, LaravelEventBus::class);

        // ═══════════════════════════════════════════════════════════════════════
        // SERVICES & CONTRACTS BINDINGS
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ReportGenerationInterface::class,
            ReportGenerationService::class
        );
        $this->app->bind(
            LoggerInterface::class,
            LaravelLoggerAdapter::class
        );
        $this->app->bind(
            \App\Application\Shared\Contracts\LoggerInterface::class,
            \App\Infrastructure\Logging\LaravelLoggerAdapter::class
        );
        $this->app->bind(
            PaymentWebhookSettlementInterface::class,
            EloquentPaymentWebhookSettlement::class
        );
        $this->app->bind(
            SubscriptionIntentStoreInterface::class,
            EloquentSubscriptionIntentStore::class
        );
        $this->app->bind(
            PrivateAiTenantAccessReaderInterface::class,
            EloquentPrivateAiTenantAccessReader::class
        );
        $this->app->bind(
            \App\Application\Ai\Security\AiContentCipher::class,
            \App\Infrastructure\AI\Security\LaravelAiContentCipher::class,
        );
        $this->app->bind(
            \App\Application\Ai\Conversation\ConversationStore::class,
            \App\Infrastructure\AI\Conversation\EloquentConversationStore::class,
        );
        $this->app->bind(
            EmbeddingProvider::class,
            OpenAiEmbeddingProvider::class,
        );
        $this->app->bind(
            KnowledgeRetriever::class,
            SemanticKnowledgeRetriever::class,
        );
        $this->app->bind(
            LeadFollowUpPlaybookInterface::class,
            EloquentLeadFollowUpPlaybook::class
        );
        $this->app->bind(
            CrmDefaultPipelineGatewayInterface::class,
            EloquentCrmDefaultPipelineGateway::class
        );
        $this->app->bind(
            CrmPipelineSummaryGatewayInterface::class,
            EloquentCrmPipelineSummaryGateway::class
        );
        $this->app->bind(
            DataScrubberInterface::class,
            SensitiveDataScrubber::class
        );
        $this->app->bind(
            MetadataSanitizerInterface::class,
            MetadataSanitizer::class
        );
        $this->app->bind(
            TenantRateLimiterInterface::class,
            CacheTenantRateLimiter::class
        );
        $this->app->bind(
            DataExportInterface::class,
            ExcelExportService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // NOTIFICATION ABSTRACTION: Decouples Filament from Business Logic
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            AdminNotifier::class,
            FilamentAdminNotifier::class
        );
        $this->app->bind(
            SearchRepository::class,
            EloquentSearchRepository::class
        );
        $this->app->bind(
            DirectoryReader::class,
            EloquentDirectoryReader::class
        );
        $this->app->bind(
            ProductCatalogReader::class,
            CachedProductReader::class
        );
        $this->app->bind(
            BillingReader::class,
            EloquentBillingReader::class
        );
        $this->app->bind(
            OnboardingReader::class,
            CachedOnboardingReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SALES DOMAIN: Order Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            OrderReader::class,
            EloquentOrderReader::class
        );
        $this->app->bind(
            ReportReader::class,
            EloquentReportReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ANALYTICS DOMAIN: Metrics Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            AnalyticsReader::class,
            EloquentAnalyticsReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PRODUCT DOMAIN: Inventory Query Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ProductReader::class,
            EloquentProductReader::class
        );
        $this->app->bind(
            TenantProductRepository::class,
            EloquentTenantProductRepository::class
        );
        // Shared Domains
        $this->app->singleton(
            TenantContext::class,
            RequestTenantContext::class
        );
        $this->app->bind(
            TenantReadRepository::class,
            EloquentTenantReadRepository::class
        );

        // Public/Storefront Domains (Decorated with Cache)
        $this->app->bind(
            TenantStorefrontRepository::class,
            function ($app) {
                // Instantiate the inner Eloquent Implementation
                $inner = $app->make(EloquentTenantStorefrontRepository::class);

                // Wrap it with the Cached Decorator
                return new CachedTenantStorefrontRepository($inner);
            }
        );
        $this->app->bind(
            LandingPageRepository::class,
            EloquentLandingPageRepository::class
        );
        $this->app->singleton(
            \App\Domain\Shared\Services\TenantContext::class,
            \App\Domain\Shared\Services\TenantContext::class
        );

        $this->app->bind(
            BuyerRepository::class,
            EloquentBuyerRepository::class
        );

        // Tenant Domains (Metrics)
        $this->app->bind(
            DashboardMetricsRepository::class,
            EloquentDashboardMetricsRepository::class
        );

        // Tenant Domains
        $this->app->bind(
            OrderRepository::class,
            EloquentOrderRepository::class
        );
        $this->app->bind(
            TenantProductRepository::class,
            EloquentTenantProductRepository::class
        );
        $this->app->bind(
            CouponRepository::class,
            EloquentCouponRepository::class
        );

        // API Domains
        $this->app->bind(
            NotificationRepository::class,
            EloquentNotificationRepository::class
        );
        $this->app->bind(
            BillingRepository::class,
            EloquentBillingRepository::class
        );
        $this->app->bind(
            PlanRepositoryInterface::class,
            ConfigPlanRepository::class
        );
        $this->app->bind(
            SubscriptionIntentRepositoryInterface::class,
            EloquentSubscriptionIntentRepository::class
        );
        $this->app->bind(
            CouponReader::class,
            EloquentCouponReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY DOMAIN
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            PasswordHasherInterface::class,
            LaravelPasswordHasher::class
        );
        $this->app->bind(
            \App\Domain\Shared\Services\PasswordHasherInterface::class,
            LaravelPasswordHasher::class
        );
        $this->app->bind(
            ValidationServiceInterface::class,
            LaravelValidationService::class
        );

        $this->app->bind(
            OutboxRepositoryInterface::class,
            EloquentOutboxRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SEARCH & PUBLIC CATALOG
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            GlobalSearchReader::class,
            EloquentGlobalSearchReader::class
        );
        $this->app->bind(
            PublicCatalogReader::class,
            EloquentPublicCatalogReader::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // COMMAND BUS: Synchronous Execution Pipeline
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            CommandBusInterface::class,
            IlluminateCommandBus::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // REPOSITORY BINDINGS: DIP Compliance for Handlers
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );
        $this->app->bind(
            TenantRepositoryInterface::class,
            EloquentTenantRepository::class
        );
        $this->app->bind(
            PaymentRepositoryInterface::class,
            EloquentPaymentRepository::class
        );
        // ═══════════════════════════════════════════════════════════════════════
        // REVIEWS DOMAIN: Repository Bindings
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ReviewRepositoryInterface::class,
            EloquentReviewRepository::class
        );
        $this->app->bind(
            ProductRatingMetricsWriter::class,
            EloquentProductRatingMetricsWriter::class
        );
        $this->app->bind(
            ProductBlurhashWriter::class,
            EloquentProductBlurhashWriter::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // PAYMENT DOMAIN: Repository Bindings
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            WebhookLogRepositoryInterface::class,
            EloquentWebhookLogRepository::class
        );
        $this->app->bind(
            \App\Domain\Billing\Repositories\WebhookLogRepositoryInterface::class,
            EloquentWebhookLogRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // IDENTITY DOMAIN: Social Identity Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            SocialIdentityRepositoryInterface::class,
            EloquentSocialIdentityRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // DOCUMENT STORAGE: Abstraction for file handling in Billing Domain
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            DocumentStorageServiceInterface::class,
            S3DocumentStorageService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ANALYTICS DOMAIN: Interaction Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            InteractionRepositoryInterface::class,
            EloquentInteractionRepository::class
        );

        $this->app->bind(
            \App\Domain\Interactions\Repositories\InteractionRepositoryInterface::class,
            \App\Infrastructure\Interactions\Persistence\EloquentInteractionRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SALES DOMAIN: Lead Repository
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            LeadRepositoryInterface::class,
            EloquentLeadRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // AUTH DOMAIN: OTP Notification and Repository Abstractions
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            OtpNotifierInterface::class,
            EmailOtpNotifier::class
        );

        // OTP Repository - Tenant-Aware Implementation (CANONICAL)
        $this->app->bind(
            OtpRepositoryInterface::class,
            EloquentOtpRepository::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // OTP CONFIGURATION: Fail-Fast Injection (No Silent Failures)
        // All values MUST be defined in config/auth.php['otp'] or boot will abort.
        // ═══════════════════════════════════════════════════════════════════════

        // GenerateOtpHandler: OTP Expiry Minutes
        $this->app->when(GenerateOtpHandler::class)
            ->needs('$otpExpiryMinutes')
            ->give(function () {
                $value = config('auth.otp.expiry_minutes');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.expiry_minutes');
                }

                return (int) $value;
            });

        // RateLimitedGenerateOtpHandler: Max Requests Per Hour
        $this->app->when(RateLimitedGenerateOtpHandler::class)
            ->needs('$maxRequestsPerHour')
            ->give(function () {
                $value = config('auth.otp.max_requests_per_hour');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.max_requests_per_hour');
                }

                return (int) $value;
            });

        // GenerateOtpHandler: OTP Length
        $this->app->when(GenerateOtpHandler::class)
            ->needs('$otpLength')
            ->give(function () {
                $value = config('auth.otp.length');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.length');
                }

                return (int) $value;
            });

        // RateLimitedGenerateOtpHandler: Rate Limit Decay Seconds
        $this->app->when(RateLimitedGenerateOtpHandler::class)
            ->needs('$decaySeconds')
            ->give(function () {
                $value = config('auth.otp.rate_limit_decay_seconds');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.rate_limit_decay_seconds');
                }

                return (int) $value;
            });

        // VerifyOtpHandler: Max Verification Attempts
        $this->app->when(VerifyOtpHandler::class)
            ->needs('$maxOtpAttempts')
            ->give(function () {
                $value = config('auth.otp.max_attempts');
                if ($value === null) {
                    throw new \RuntimeException('Critical configuration missing: auth.otp.max_attempts');
                }

                return (int) $value;
            });

        // VerifyOtpRateLimitDecorator: Rate Limit Config
        $this->app->when(VerifyOtpRateLimitDecorator::class)
            ->needs('$maxAttempts')
            ->give((int) config('auth.otp.max_attempts', 5));

        $this->app->when(VerifyOtpRateLimitDecorator::class)
            ->needs('$decaySeconds')
            ->give(60);

        // ═══════════════════════════════════════════════════════════════════════
        // AUTH DOMAIN: VERIFY OTP PIPELINE (Standardized)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            VerifyOtpHandlerInterface::class,
            function ($app) {
                return PipelineBuilder::make(VerifyOtpHandler::class)
                    ->through([
                        // Outermost (Runs First)
                        VerifyOtpRateLimitDecorator::class,
                        VerifyOtpCommandValidator::class,
                        TransactionVerifyOtpHandler::class,
                    ])
                    ->build($app);
            }
        );

        // LoginViaSocialProvider Pipeline
        $this->app->bind(
            LoginViaSocialProviderHandlerInterface::class,
            function ($app) {
                // IMPORTANT: Core is resolved by PipelineBuilder internally
                return PipelineBuilder::make(LoginViaSocialProviderHandler::class)
                    ->through([
                        LoginViaSocialProviderCommandValidator::class,
                        AtomicIdempotencyDecorator::class,
                        TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // DeleteUserHandler Pipeline
        $this->app->bind(
            DeleteUserHandlerInterface::class,
            function ($app) {
                return PipelineBuilder::make(DeleteUserHandler::class)
                    ->through([
                        DeleteUserCommandValidator::class,
                        AtomicIdempotencyDecorator::class,
                        TransactionDecorator::class,
                    ])
                    ->build($app);
            }
        );

        // UpdateUserPassword Pipeline (Standardized)
        $this->app->bind(
            UpdateUserPasswordHandlerInterface::class,
            function ($app) {
                return PipelineBuilder::make(UpdateUserPasswordHandler::class)
                    ->through([
                        TransactionDecorator::class,
                        UpdateUserPasswordCommandValidator::class,
                    ])
                    ->build($app);
            }
        );

        // GenerateOtp Pipeline (Consolidated & Standardized)
        $this->app->bind(
            GenerateOtpHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                // Resolved via Container to harness contextual bindings defined above
                // PipelineBuilder uses $app->make($coreClass) internally, so we just pass the class name.
                // However, Wait. PipelineBuilder::make() takes a string class name OR an instance?
                // My PipelineBuilder::make takes string.
                // Does it verify contextual bindings? Yes, $container->make($class) respects them.

                return PipelineBuilder::make(GenerateOtpHandler::class)
                    ->through([
                        RateLimitedGenerateOtpHandler::class, // Outer
                        ValidationGenerateOtpHandler::class,  // Gate
                        \App\Application\Auth\Decorators\AtomicIdempotencyDecorator::class,    // Lock
                        TransactionGenerateOtpHandler::class, // ACID Transaction (Strict Typed)
                    ])
                    ->build($app);
            }
        );

        // IdempotentGenerateOtpHandler: Idempotency TTL (Fail-Fast)
        $this->app->when(IdempotentGenerateOtpHandler::class)
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
            AdminNotifierInterface::class,
            FilamentAdminNotifier::class
        );

        // System Failure Notifier (SRE Hardening)
        $this->app->bind(
            FailureNotifierInterface::class,
            LogFailureNotifier::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // BILLING DOMAIN: Subscription Gateway Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            SubscriptionGatewayInterface::class,
            ManualSubscriptionGateway::class
        );
        $this->app->bind(
            SubscriptionCheckoutGatewayInterface::class,
            fn () => strtolower((string) config('services.payment.provider', 'generic')) === 'stripe'
                ? new StripeSubscriptionCheckoutGateway
                : new ManualSubscriptionCheckoutGateway
        );

        $this->app->when(PaymentWebhookController::class)
            ->needs('$webhookSecret')
            ->give(fn () => strtolower((string) config('services.payment.provider', 'generic')) === 'stripe'
                ? (string) config('services.stripe.webhook_secret', '')
                : (string) config('services.payment.webhook_secret', ''));

        // ═══════════════════════════════════════════════════════════════════════
        // BILLING DOMAIN: Handler Decorator Pipelines (Validation Layer)
        // ═══════════════════════════════════════════════════════════════════════

        // ReportPayment Pipeline: Validator → Handler (Core)
        $this->app->bind(
            ReportPaymentHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                $core = $app->make(ReportPaymentHandler::class);

                // LAYER 1: Validation Decorator (Now Outermost)
                return new ReportPaymentCommandValidator(
                    inner: $core,
                    validatorFactory: $app->make(Factory::class)
                );
            }
        );

        // RequestUpgrade Pipeline: Validator → Handler (Core)
        $this->app->bind(
            RequestUpgradeHandlerInterface::class,
            function ($app) {
                // CORE: Pure Business Logic
                $core = $app->make(RequestUpgradeHandler::class);

                // LAYER 1: Validation Decorator (Outermost)
                return new RequestUpgradeCommandValidator(
                    inner: $core,
                    validatorFactory: $app->make(Factory::class)
                );
            }
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SHARED: Media Storage Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            MediaStorageServiceInterface::class,
            LaravelMediaStorageService::class
        );
        $this->app->bind(
            ProductImageStorageInterface::class,
            NormalizedProductImageStorage::class
        );
        // Shared AI execution boundary. Adapter keys are registry identifiers;
        // credential values stay inside deployment configuration.
        $this->app->bind(
            AiProviderPool::class,
            static function ($app): AiProviderPool {
                $reference = trim((string) config('ai.registry.openai_secret_reference', ''));
                $credential = trim((string) config('services.openai.api_key', ''));
                $provider = (bool) config('ai.providers.openai.enabled', false)
                    && $reference !== ''
                    && $credential !== ''
                        ? $app->make(OpenAiStructuredTextProvider::class)
                        : $app->make(DisabledAiProvider::class);

                return new ConfiguredAiProviderPool([
                    'openai_structured_text' => [
                        $reference => $provider,
                    ],
                ]);
            }
        );
        $this->app->bind(
            StructuredOutputValidator::class,
            LaravelStructuredOutputValidator::class
        );
        $this->app->bind(
            AiGateway::class,
            ResilientAiGateway::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // ORDERS DOMAIN: Receipt PDF Generator
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ReceiptGeneratorInterface::class,
            DomPdfReceiptGenerator::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: Rate Limiting Abstraction
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            RateLimitServiceInterface::class,
            LaravelRateLimitService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // TRANSACTIONAL OUTBOX: Event Dispatcher with At-Least-Once Delivery
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->singleton(
            TransactionalEventDispatcher::class,
            TransactionalEventDispatcher::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // FORTIFY ACTIONS: Password Reset & User Authentication
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            ResetsUserPasswords::class,
            ResetPasswordAction::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // INFRASTRUCTURE: Fundamentals (Time, Entropy, Transactions, Outbox)
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            \App\Application\Shared\Contracts\OutboxRepositoryInterface::class,
            SchemaValidatingOutboxRepository::class
        );
        $this->app->bind(
            ClockInterface::class,
            SystemClock::class
        );
        $this->app->bind(
            EntropyGeneratorInterface::class,
            NativeEntropyGenerator::class
        );
        $this->app->bind(
            OtpHasherInterface::class,
            HmacOtpHasher::class
        );
        $this->app->bind(
            TransactionManagerInterface::class,
            DatabaseTransactionManager::class
        );

        $this->app->bind(
            UuidGeneratorInterface::class,
            RamseyUuidGenerator::class
        );

        $this->app->bind(
            EventDispatcherInterface::class,
            TransactionalEventDispatcher::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // INFRASTRUCTURE: Resilience & Observability
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->scoped(
            TraceContext::class,
            TraceContext::class
        );
        $this->app->scoped(
            TraceContextInterface::class,
            TraceContext::class
        );

        $this->app->bind(
            MetricsCollectorInterface::class,
            DatadogMetricsAdapter::class
        );

        $this->app->bind(
            CircuitBreakerInterface::class,
            function ($app) {
                return new RedisCircuitBreaker(
                    redis: $app['redis']->connection(), // Explicit Connection Resolution
                    metrics: $app->make(MetricsCollectorInterface::class)
                );
            }
        );

        $this->app->bind(
            AtomicIdempotencyInterface::class,
            AtomicIdempotencyService::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // DOMAIN EVENTS: Infrastructure Bridge
        // ═══════════════════════════════════════════════════════════════════════
        $this->app->bind(
            DomainEventBus::class,
            LaravelEventBus::class
        );

        // ═══════════════════════════════════════════════════════════════════════
        // IDEMPOTENCY: Titanium Stability (AUTH & IDENTITY)
        // ═══════════════════════════════════════════════════════════════════════

        // 1. CONTEXT PROVIDER (Polymorphic Identity)
        $this->app->bind(
            ClientContextProviderInterface::class,
            function ($app) {
                return $app->runningInConsole()
                    ? new ConsoleContextProvider
                    : new RequestContextProvider($app['request']);
            }
        );

        // 3. IDENTITY DOMAIN DECORATOR STACK (The Onion)
        // Order: Security -> Observability -> Consistency -> Core
        $this->app->bind(
            RegisterUserHandlerInterface::class,
            function ($app) {
                // CORE: The Business Logic (Pure)
                $core = $app->make(RegisterUserHandler::class);

                // LAYER 1: Idempotency (The Guard) - Protects Core from duplicates
                // Uses the container-resolved IdempotencyService
                $idempotent = new IdempotentRegisterUserHandler(
                    inner: $core,
                    service: $app->make(AtomicIdempotencyInterface::class)
                );

                // LAYER 2: Telemetry (The Watcher) - Measures Total Execution (including Idempotency check)
                $telemetry = new TelemetryRegisterUserHandler(
                    inner: $idempotent,
                    logger: $app->make(\Psr\Log\LoggerInterface::class)
                );

                // LAYER 3: Security (The Shield) - OUTERMOST LAYER
                // This runs first! (Fail Fast Principle)
                return new RateLimitedRegisterUserHandler(
                    inner: $telemetry,
                    // Use Interface
                    rateLimiter: $app->make(RateLimiterInterface::class),
                    contextProvider: $app->make(ClientContextProviderInterface::class)
                );
            }
        );

        $this->app->bind(
            RateLimiterInterface::class,
            LaravelRateLimiterAdapter::class
        );

        // 4. SALES DOMAIN: Idempotency Protection
        $this->app->bind(
            CreateOrderHandlerInterface::class,
            function ($app) {
                $core = $app->make(CreateOrderHandler::class);

                return new IdempotentCreateOrderHandler(
                    inner: $core,
                    service: $app->make(IdempotencyServiceInterface::class)
                );
            }
        );

        // 5. TENANCY DOMAIN: Idempotency Protection
        $this->app->bind(
            RegisterTenantHandlerInterface::class,
            function ($app) {
                $core = $app->make(RegisterTenantHandler::class);

                return new IdempotentRegisterTenantHandler(
                    inner: $core,
                    service: $app->make(IdempotencyServiceInterface::class)
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
        // LOCALIZATION: Sync Carbon locale with Laravel app.locale
        // ═══════════════════════════════════════════════════════════════════════
        // Carbon does NOT auto-sync with config('app.locale'). This ensures all
        // date formatting (diffForHumans, translatedFormat) respects the locale.
        // ═══════════════════════════════════════════════════════════════════════
        Carbon::setLocale(config('app.locale'));

        // ═══════════════════════════════════════════════════════════════════════
        // POLYMORPHIC INTEGRITY: Morph Map for Domain Migration
        // ═══════════════════════════════════════════════════════════════════════
        // CRITICAL: This mapping MUST be registered BEFORE moving model files.
        // It decouples the database's model_type column from the PHP namespace.
        // Without this, activity_log, media, comments and other polymorphic
        // tables would have orphaned records pointing to non-existent classes.
        // ═══════════════════════════════════════════════════════════════════════
        Relation::enforceMorphMap([
            'user' => User::class,
            'tenant' => Tenant::class,
            'product' => Product::class,
            'order' => Order::class,
            'appointment' => Appointment::class,
            'quote' => Quote::class,
            'coupon' => Coupon::class,
            'crm_deal' => CrmDeal::class,
            'customer_note' => CustomerNote::class,
            'customer_task' => CustomerTask::class,
            'service' => Service::class,
            'service_provider' => ServiceProviderModel::class,
            'payment' => Payment::class,
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
        // These aliases redirect to new App\Domain\*\Jobs\* locations.
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);

        // Core Models Policies
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(MenuSection::class, MenuSectionPolicy::class);

        // Observers
        Product::observe(ProductObserver::class);
        Product::observe(KnowledgeIndexObserver::class);
        Service::observe(KnowledgeIndexObserver::class);
        Tenant::observe(TenantObserver::class);
        Tenant::observe(KnowledgeIndexObserver::class);
        Order::observe(CommercialOutcomeObserver::class);
        Appointment::observe(CommercialOutcomeObserver::class);

        // ═══════════════════════════════════════════════════════════════════════
        // SECURITY: Rate Limiters
        // ═══════════════════════════════════════════════════════════════════════
        RateLimiter::for('analytics-protection', function (Request $request) {
            // Composite Key: IP + User Agent + Target Tenant
            // Prevents botnet distributed attacks against a specific tenant.
            $key = $request->ip().'|'.$request->userAgent().'|'.$request->input('tenant_id');

            return Limit::perMinute(10)->by($key);
        });

        // TENANT-AWARE RATE LIMITING (Contextual Throttling)
        RateLimiter::for('tenant-aware', function (Request $request) {
            // Resolve Tenant ID securely from Context if available
            // If explicit 'tenant_id' input is missing, try to resolve from route or attributes
            // Fallback to IP if purely anonymous without context

            /** @var TenantContext $context */
            $context = app(TenantContext::class);
            try {
                $tenantId = $context->getTenantId();
            } catch (\Throwable) {
                // Public analytics has no authenticated user context by design.
                $tenantId = null;
            }

            $tenantId ??= $request->input('tenant_id');

            $key = $tenantId
                ? "tenant_id:{$tenantId}|ip:".$request->ip()
                : 'ip:'.$request->ip();

            return Limit::perMinute(120)->by($key); // Increased to 120 as per prompt example
        });

        // ═══════════════════════════════════════════════════════════════════════
        // BUS CONFIGURATION: Middleware & Mappings
        // ═══════════════════════════════════════════════════════════════════════
        Bus::pipeThrough([
            TransactionalMiddleware::class,
        ]);

        Bus::map([
            RegisterUserCommand::class => RegisterUserHandlerInterface::class,
            CreateOrderCommand::class => CreateOrderHandler::class,
            RegisterTenantCommand::class => RegisterTenantHandler::class,
            // \App\Application\Identity\Commands\RegisterUserCommand::class => \App\Application\Identity\Handlers\RegisterUserHandler::class, (Replaced by Decorator Stack)
            ReportPaymentCommand::class => ReportPaymentHandlerInterface::class,
            SubmitReviewCommand::class => SubmitReviewHandler::class,
            CancelOrderCommand::class => CancelOrderHandler::class,
            HandlePaymentWebhookCommand::class => HandlePaymentWebhookHandler::class,
            RequestUpgradeCommand::class => RequestUpgradeHandlerInterface::class,
            LoginViaSocialProviderCommand::class => LoginViaSocialProviderHandlerInterface::class,
            UpdateUserPasswordCommand::class => UpdateUserPasswordHandlerInterface::class,
            UpdateUserProfileCommand::class => UpdateUserProfileHandler::class, // Left as is for now
            DeleteUserCommand::class => DeleteUserHandlerInterface::class,
            GenerateOtpCommand::class => GenerateOtpHandlerInterface::class,
            VerifyOtpCommand::class => VerifyOtpHandlerInterface::class,
            UpdateTenantScheduleCommand::class => UpdateTenantScheduleHandler::class,
            UpdateLandingPageCommand::class => UpdateLandingPageHandler::class,
            UpdateTenantBrandingCommand::class => UpdateTenantBrandingHandler::class,
            RecordInteractionCommand::class => RecordInteractionHandler::class,
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // EVENT LISTENERS: Eventual Consistency Handlers
        // ═══════════════════════════════════════════════════════════════════════
        Event::listen(
            ReviewCreated::class,
            UpdateProductRating::class
        );
        Event::listen(
            OrderCancelled::class,
            RestoreStockOnCancellation::class
        );
        foreach ([
            OrderPaid::class,
            OrderDelivered::class,
            OrderCancelled::class,
        ] as $commercialOutcomeEvent) {
            Event::listen(
                $commercialOutcomeEvent,
                ProjectCommercialOutcomeFromOrderEvent::class,
            );
        }
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
            'App\\Jobs\\GeneratePdfReportJob' => GeneratePdfReportJob::class,
            'App\\Jobs\\GenerateSalesExport' => GenerateSalesExport::class,
            // REMOVED: 'App\\Jobs\\GenerateSalesReportJob' - Legacy proxy deleted
            // Legacy Root to Application (Double Jump Protection)
            'App\\Jobs\\SendCampaignEmail' => SendCampaignEmail::class,
            'App\\Jobs\\GenerateProductBlurhash' => GenerateProductBlurhash::class,
            'App\\Jobs\\EnhanceTenantProfileJob' => EnhanceTenantProfileJob::class,
            'App\\Jobs\\GenerateBusinessProfile' => GenerateBusinessProfile::class,
            'App\\Jobs\\RecordInteraction' => RecordInteraction::class,
            'App\\Jobs\\SyncAnalyticsJob' => SyncAnalyticsJob::class,
            'App\\Jobs\\ReleaseStaleOrders' => ReleaseStaleOrders::class,
            'App\\Jobs\\AuditTenantLimits' => AuditTenantLimits::class,

            // Domain to Application/Infrastructure (The Great Migration)
            'App\\Domain\\Marketing\\Jobs\\SendCampaignEmail' => SendCampaignEmail::class,
            'App\\Domain\\Analytics\\Jobs\\RecordInteraction' => RecordInteraction::class,
            'App\\Domain\\Analytics\\Jobs\\SyncAnalyticsJob' => SyncAnalyticsJob::class,
            'App\\Domain\\Analytics\\Jobs\\SyncTenantAnalytics' => SyncTenantAnalytics::class,
            'App\\Domain\\TenantGovernance\\Jobs\\AuditTenantLimits' => AuditTenantLimits::class,
            'App\\Domain\\TenantGovernance\\Jobs\\ReleaseStaleOrders' => ReleaseStaleOrders::class,
            'App\\Domain\\AIEnrichment\\Jobs\\EnhanceTenantProfileJob' => EnhanceTenantProfileJob::class,
            'App\\Domain\\AIEnrichment\\Jobs\\GenerateBusinessProfile' => GenerateBusinessProfile::class,
            'App\\Domain\\MediaProcessing\\Jobs\\GenerateProductBlurhash' => GenerateProductBlurhash::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GeneratePdfReportJob' => GeneratePdfReportJob::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GenerateSalesExport' => GenerateSalesExport::class,
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
