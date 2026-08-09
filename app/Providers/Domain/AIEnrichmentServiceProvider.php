<?php

declare(strict_types=1);

/**
 * AIEnrichmentServiceProvider - Domain Wiring
 *
 * PURPOSE: Register AI service contracts and event listeners.
 * MIGRATED: From App\Domain\AIEnrichment to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use App\Application\Ai\Catalog\CatalogImportPublisherInterface;
use App\Application\Ai\Catalog\CatalogVisionProviderInterface;
use App\Application\Ai\Workforce\AiRegistryWorkforceAuthority;
use App\Application\Ai\Workforce\Contracts\WorkforceRegistryAuthority;
use App\Application\BusinessGraph\Contracts\BusinessGraphRepositoryInterface;
use App\Infrastructure\AI\LaravelCatalogImportPublisher;
use App\Infrastructure\AI\OpenAiCatalogVisionProvider;
use App\Infrastructure\BusinessGraph\DatabaseBusinessGraphRepository;
use Illuminate\Support\ServiceProvider;

final class AIEnrichmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogVisionProviderInterface::class, OpenAiCatalogVisionProvider::class);
        $this->app->bind(CatalogImportPublisherInterface::class, LaravelCatalogImportPublisher::class);
        $this->app->bind(WorkforceRegistryAuthority::class, AiRegistryWorkforceAuthority::class);
        $this->app->bind(BusinessGraphRepositoryInterface::class, DatabaseBusinessGraphRepository::class);
    }

    public function boot(): void
    {
        // Register AI enrichment event listeners
    }
}
