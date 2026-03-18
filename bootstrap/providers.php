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
    App\Providers\TypeScriptTransformerServiceProvider::class,
    App\Providers\BusServiceProvider::class, // Command Bus Middleware Pipeline
    App\Providers\FortifyServiceProvider::class, // Security Actions
    App\Providers\InfrastructureServiceProvider::class,
    App\Providers\EventServiceProvider::class, // Central Event Hub (Projections)
    App\Providers\TitaniumServiceProvider::class, // Titanium Standard (ACID, Outbox, Idempotency)
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\AppPanelProvider::class,
    App\Providers\TelescopeServiceProvider::class,

    // ═══════════════════════════════════════════════════════════════════════
    // DOMAIN PROVIDERS: Migrated to App\Providers\Domain (2026-01-07)
    // ═══════════════════════════════════════════════════════════════════════
    App\Providers\Domain\IdentityServiceProvider::class,
    App\Providers\Domain\TenantGovernanceServiceProvider::class,
    App\Providers\Domain\MarketingServiceProvider::class,
    App\Providers\Domain\AIEnrichmentServiceProvider::class,
    App\Providers\Domain\AnalyticsServiceProvider::class,
    App\Providers\Domain\ReportingServiceProvider::class,
    App\Providers\Domain\MediaProcessingServiceProvider::class,
    App\Providers\SchemaRegistryServiceProvider::class, // Schema Registry (Contract Testing)
    App\Providers\AuthDomainServiceProvider::class,
];
