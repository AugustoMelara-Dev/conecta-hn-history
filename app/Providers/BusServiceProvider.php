<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Shared\Middleware\TransactionMiddleware;
use App\Application\Shared\Middleware\ValidationMiddleware;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\ServiceProvider;

/**
 * BusServiceProvider - Command Bus Governance
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PIPELINE: Defines the global decoration chain for all Commands.         ║
 * ║  ORDER: Validation -> Transaction -> Handler.                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BusServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Bus::pipeThrough([
            // 1. Validation: Fail fast before touching DB
            ValidationMiddleware::class,

            // 2. Transaction: Wrap execution in atomic block
            //    (Includes Outbox Event Persistence)
            TransactionMiddleware::class,
        ]);
    }
}
