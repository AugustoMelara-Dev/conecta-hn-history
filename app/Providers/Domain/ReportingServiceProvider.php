<?php

declare(strict_types=1);

/**
 * ReportingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register reporting and export contracts.
 * MIGRATED: From App\Domain\ReportingAndExports to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

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
