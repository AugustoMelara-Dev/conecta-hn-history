<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Common\Interfaces\IdempotencyRepositoryInterface;
use App\Application\Shared\Contracts\TransactionManagerInterface;
use App\Infrastructure\Common\LaravelTransactionManager;
use App\Infrastructure\Common\Persistence\EloquentIdempotencyRepository;
use Illuminate\Support\ServiceProvider;

/**
 * TitaniumServiceProvider
 *
 * Registers the critical infrastructure components for the Titanium Standard architecture.
 */
class TitaniumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Transaction Manager (if not already bound elsewhere)
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);

        // Bind Idempotency (SQL Implementation)
        $this->app->bind(IdempotencyRepositoryInterface::class, EloquentIdempotencyRepository::class);

    }
}
