<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Middleware;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * TransactionalMiddleware - Automated ACID Boundary
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRATEGY A: AUTOMATED ACIDITY                                           ║
 * ║  Wraps execution in Database Transaction.                                ║
 * ║  FAIL-SAFE: Automatically rolls back on any exception.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class TransactionalMiddleware
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $next($command);

            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}
