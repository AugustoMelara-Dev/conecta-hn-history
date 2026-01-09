<?php

declare(strict_types=1);

/**
 * MarketingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings and events for Marketing campaigns and emails.
 * MIGRATED: From App\Domain\Marketing to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use Illuminate\Support\ServiceProvider;

final class MarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind domain contracts
    }

    public function boot(): void
    {
        // Register campaign event listeners
    }
}
