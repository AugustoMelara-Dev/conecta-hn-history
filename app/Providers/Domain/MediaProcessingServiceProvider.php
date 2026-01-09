<?php

declare(strict_types=1);

/**
 * MediaProcessingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register media processing contracts and event listeners.
 * MIGRATED: From App\Domain\MediaProcessing to App\Providers\Domain (2026-01-07)
 */

namespace App\Providers\Domain;

use Illuminate\Support\ServiceProvider;

final class MediaProcessingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind media processing contracts
    }

    public function boot(): void
    {
        // Register blurhash and image processing listeners
    }
}
