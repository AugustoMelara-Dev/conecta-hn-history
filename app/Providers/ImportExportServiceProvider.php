<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * ImportExportServiceProvider - Registers import/export services.
 */
class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Import classes are auto-discovered via ImportService; this provider
        // exists to register additional bindings (queue, storage, exporters).
    }

    public function boot(): void
    {
        // No-op for now
    }
}
