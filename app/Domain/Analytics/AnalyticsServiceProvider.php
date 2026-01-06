<?php

declare(strict_types=1);

/**
 * AnalyticsServiceProvider - Domain Wiring
 *
 * PURPOSE: Register analytics contracts and sync jobs.
 */

namespace App\Domain\Analytics;

use Illuminate\Support\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind analytics contracts
    }

    public function boot(): void
    {
        // Register analytics event listeners
    }
}
