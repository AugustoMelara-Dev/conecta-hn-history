<?php

declare(strict_types=1);

namespace App\Application\Shared\Middleware;

use App\Application\Shared\Contracts\TransactionManagerInterface;
use Closure;

/**
 * TransactionMiddleware - Atomic Execution Wrapper
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACID GUARANTEE: Wraps entire handler execution in a DB transaction.    ║
 * ║  FRAMEWORK AGNOSTIC: Uses TransactionManagerInterface instead of DB.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class TransactionMiddleware
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        // PERFORMANCE OPTIMIZATION: Skip transaction for Read-Only operations
        if ($command instanceof \App\Application\Shared\Contracts\ReadOnlyCommandInterface) {
            return $next($command);
        }

        // FRAMEWORK AGNOSTIC: Delegate to injected TransactionManager
        return $this->transactionManager->transaction(
            fn () => $next($command)
        );
    }
}
