<?php

declare(strict_types=1);

namespace App\Application\Shared\Middleware;

use Illuminate\Support\Facades\DB;
use Closure;

/**
 * TransactionMiddleware - Atomic Execution Wrappers
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ACID GUARANTEE: Wraps entire handler execution in a DB transaction.    ║
 * ║  ATOMIC OUTBOX: Events dispatched within are committed atomically.      ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class TransactionMiddleware
{
    public function handle(object $command, Closure $next): mixed
    {
        // PERFORMANCE OPTIMIZATION: Skip transaction for Read-Only operations
        if ($command instanceof \App\Application\Shared\Contracts\ReadOnlyCommand) {
             return $next($command);
        }

        // MANUAL TRANSACTION MANAGEMENT
        // Requirement: "Este middleware debe envolver... dentro de un bloque DB::beginTransaction() y DB::commit()"
        DB::beginTransaction();

        try {
            $result = $next($command);
            
            DB::commit();
            
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            
            throw $e;
        }
    }
}
