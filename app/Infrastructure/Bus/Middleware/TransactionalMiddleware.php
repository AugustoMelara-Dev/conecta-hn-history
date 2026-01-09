<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Middleware;

use Illuminate\Support\Facades\DB;
use Closure;

/**
 * TransactionalMiddleware
 * 
 * DECORATOR: Wraps the command execution in a Database Transaction.
 * Ensures strict atomicity for all Write Operations (Commands).
 */
final class TransactionalMiddleware
{
    /**
     * Handle the command.
     *
     * @param  object  $command
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($command, Closure $next)
    {
        // Execute the handler within a transaction
        // If an exception triggers, rollback occurs automatically.
        // If success, commit occurs automatically.
        return DB::transaction(fn () => $next($command));
    }
}
