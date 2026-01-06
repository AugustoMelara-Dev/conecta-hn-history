<?php

declare(strict_types=1);

/**
 * ReportingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register reporting and export contracts.
 */

namespace App\Domain\ReportingAndExports;

use Illuminate\Support\ServiceProvider;

final class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind reporting contracts
    }

    public function boot(): void
    {
        // Register export event listeners
    }
}
