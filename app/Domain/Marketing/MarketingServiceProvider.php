<?php

declare(strict_types=1);

/**
 * MarketingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register bindings and events for Marketing campaigns and emails.
 */

namespace App\Domain\Marketing;

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
