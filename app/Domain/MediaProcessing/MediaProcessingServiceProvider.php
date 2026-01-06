<?php

declare(strict_types=1);

/**
 * MediaProcessingServiceProvider - Domain Wiring
 *
 * PURPOSE: Register media processing contracts and event listeners.
 */

namespace App\Domain\MediaProcessing;

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
