<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthDomainServiceProvider;
use App\Providers\BusServiceProvider;
use App\Providers\Domain\AIEnrichmentServiceProvider;
use App\Providers\Domain\AnalyticsServiceProvider;
use App\Providers\Domain\DirectoryServiceProvider;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\Domain\MarketingServiceProvider;
use App\Providers\Domain\MediaProcessingServiceProvider;
use App\Providers\Domain\ReportingServiceProvider;
use App\Providers\Domain\SalesServiceProvider;
use App\Providers\Domain\TenantGovernanceServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\InfrastructureServiceProvider;
use App\Providers\SchemaRegistryServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\TitaniumServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

/**
 * Service Providers Registry (Laravel 12)
 *
 * ARCHITECTURE: Domain providers are registered here to enable
 * auto-discovery of bindings, events, and policies within each
 * Bounded Context.
 */
$providers = [
    // Framework Providers
    AppServiceProvider::class,
    BusServiceProvider::class, // Command Bus Middleware Pipeline
    FortifyServiceProvider::class, // Security Actions
    InfrastructureServiceProvider::class,
    EventServiceProvider::class, // Central Event Hub (Projections)
    TitaniumServiceProvider::class, // Titanium Standard (ACID, Outbox, Idempotency)
    HorizonServiceProvider::class, // Redis queue supervision (production dependency)
    AdminPanelProvider::class,
    AppPanelProvider::class,

    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    // DOMAIN PROVIDERS: Migrated to App\Providers\Domain (2026-01-07)
    // â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
    IdentityServiceProvider::class,
    TenantGovernanceServiceProvider::class,
    MarketingServiceProvider::class,
    AIEnrichmentServiceProvider::class,
    AnalyticsServiceProvider::class,
    DirectoryServiceProvider::class,
    SalesServiceProvider::class,
    ReportingServiceProvider::class,
    MediaProcessingServiceProvider::class,
    SchemaRegistryServiceProvider::class, // Schema Registry (Contract Testing)
    AuthDomainServiceProvider::class,
];

if (class_exists(TelescopeApplicationServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
