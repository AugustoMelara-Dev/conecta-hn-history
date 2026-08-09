<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Imports\Commands\GenerateImportTemplate;
use App\Application\Imports\Commands\RunImportAsync;
use App\Application\Imports\Commands\RunImportSync;
use Illuminate\Support\ServiceProvider;

/**
 * ImportExportServiceProvider - Registers import/export services.
 */
class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Concrete canonical services are container-resolvable without aliases.
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateImportTemplate::class,
                RunImportAsync::class,
                RunImportSync::class,
            ]);
        }
    }
}
