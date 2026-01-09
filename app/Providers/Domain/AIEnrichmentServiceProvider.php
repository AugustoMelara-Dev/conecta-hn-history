<?php

declare(strict_types=1);

/**
 * AIEnrichmentServiceProvider - Domain Wiring
 *
 * PURPOSE: Register AI service contracts and event listeners.
 * MIGRATED: From App\Domain\AIEnrichment to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use Illuminate\Support\ServiceProvider;

final class AIEnrichmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind AI provider contracts
    }

    public function boot(): void
    {
        // Register AI enrichment event listeners
    }
}
