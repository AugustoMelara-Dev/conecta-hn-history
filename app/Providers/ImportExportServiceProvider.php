<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Imports\Imports\AppointmentsImport;
use App\Application\Imports\Imports\CompaniesImport;
use App\Application\Imports\Imports\ContactsImport;
use App\Application\Imports\Imports\CouponsImport;
use App\Application\Imports\Imports\DealsImport;
use App\Application\Imports\Imports\OrdersImport;
use App\Application\Imports\Imports\ProductsImport;
use App\Application\Imports\Imports\UsersImport;
use App\Application\Imports\Imports\CustomersImport;
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
