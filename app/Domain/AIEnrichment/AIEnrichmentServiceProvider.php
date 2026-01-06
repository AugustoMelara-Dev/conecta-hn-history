<?php

declare(strict_types=1);

/**
 * AIEnrichmentServiceProvider - Domain Wiring
 *
 * PURPOSE: Register AI service contracts and event listeners.
 */

namespace App\Domain\AIEnrichment;

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
