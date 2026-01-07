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
            'user' => \App\Domain\Identity\Models\User::class,
            'tenant' => \App\Domain\TenantGovernance\Models\Tenant::class,
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
        Gate::policy(\App\Domain\Identity\Models\User::class, UserPolicy::class);
        Gate::policy(\App\Domain\TenantGovernance\Models\Tenant::class, TenantPolicy::class);

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
        \App\Domain\TenantGovernance\Models\Tenant::observe(\App\Observers\TenantObserver::class);
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
            'App\\Jobs\\GeneratePdfReportJob' => \App\Application\Jobs\Reporting\GeneratePdfReportJob::class,
            'App\\Jobs\\GenerateSalesExport' => \App\Application\Jobs\Exports\GenerateSalesExport::class,
            'App\\Jobs\\GenerateSalesReportJob' => \App\Domain\ReportingAndExports\Jobs\GenerateSalesReportJob::class,
            // Legacy Root to Application (Double Jump Protection)
            'App\\Jobs\\SendCampaignEmail' => \App\Infrastructure\Jobs\Marketing\SendCampaignEmail::class,
            'App\\Jobs\\GenerateProductBlurhash' => \App\Infrastructure\Jobs\Media\GenerateProductBlurhash::class,
            'App\\Jobs\\EnhanceTenantProfileJob' => \App\Application\Jobs\AI\EnhanceTenantProfileJob::class,
            'App\\Jobs\\GenerateBusinessProfile' => \App\Application\Jobs\AI\GenerateBusinessProfile::class,
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
            'App\\Domain\\AIEnrichment\\Jobs\\EnhanceTenantProfileJob' => \App\Application\Jobs\AI\EnhanceTenantProfileJob::class,
            'App\\Domain\\AIEnrichment\\Jobs\\GenerateBusinessProfile' => \App\Application\Jobs\AI\GenerateBusinessProfile::class,
            'App\\Domain\\MediaProcessing\\Jobs\\GenerateProductBlurhash' => \App\Infrastructure\Jobs\Media\GenerateProductBlurhash::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GeneratePdfReportJob' => \App\Application\Jobs\Reporting\GeneratePdfReportJob::class,
            'App\\Domain\\ReportingAndExports\\Jobs\\GenerateSalesExport' => \App\Application\Jobs\Exports\GenerateSalesExport::class,
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
     * DOMAIN MIGRATION SAFETY:
     * These aliases maintain backward compatibility during gradual transition.
     *
     * @deprecated Remove after 2026-03-05 (60 days post-migration)
     */
    protected function registerModelAliases(): void
    {
        $aliases = [
            // Identity Domain
            'App\\Models\\User' => \App\Domain\Identity\Models\User::class,

            // TenantGovernance Domain
            'App\\Models\\Tenant' => \App\Domain\TenantGovernance\Models\Tenant::class,
        ];

        foreach ($aliases as $oldClass => $newClass) {
            if (! class_exists($oldClass)) {
                class_alias($newClass, $oldClass);
            }
        }
    }
}
