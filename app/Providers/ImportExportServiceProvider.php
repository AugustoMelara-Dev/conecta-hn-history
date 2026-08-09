<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Console\Commands\GenerateImportTemplateCommand;
use App\Infrastructure\Console\Commands\RunImportAsyncCommand;
use App\Infrastructure\Console\Commands\RunImportSyncCommand;
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
                GenerateImportTemplateCommand::class,
                RunImportAsyncCommand::class,
                RunImportSyncCommand::class,
            ]);
        }
    }
}
