<?php

/**
 * Service Providers Registry (Laravel 12)
 *
 * ARCHITECTURE: Domain providers are registered here to enable
 * auto-discovery of bindings, events, and policies within each
 * Bounded Context.
 */

return [
    // Framework Providers
    App\Providers\AppServiceProvider::class,
    App\Providers\InfrastructureServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\AppPanelProvider::class,
    App\Providers\TelescopeServiceProvider::class,

    // ═══════════════════════════════════════════════════════════════════════
    // DOMAIN PROVIDERS: One per Bounded Context
    // ═══════════════════════════════════════════════════════════════════════
    App\Domain\Identity\IdentityServiceProvider::class,
    App\Domain\TenantGovernance\TenantGovernanceServiceProvider::class,
    App\Domain\Marketing\MarketingServiceProvider::class,
    App\Domain\AIEnrichment\AIEnrichmentServiceProvider::class,
    App\Domain\Analytics\AnalyticsServiceProvider::class,
    App\Domain\ReportingAndExports\ReportingServiceProvider::class,
    App\Domain\MediaProcessing\MediaProcessingServiceProvider::class,
];
