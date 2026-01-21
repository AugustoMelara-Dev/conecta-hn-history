<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

use Closure;

/**
 * AtomicIdempotencyInterface - The "Insert-or-Die" Contract
 *
 * This contract enforces a strict database-driven mutex pattern.
 * Implementations MUST use the underlying storage's unique constraint
 * to prevent race conditions.
 */
interface AtomicIdempotencyInterface
{
    /**
     * Execute a domain operation atomically.
     *
     * @param string $idempotencyKey The unique client-provided key
     * @param string $tenantId The tenant context
     * @param string $commandHash SHA-256 hash of the command payload for integrity
     * @param Closure $operation The business logic to execute if lock is acquired
     * @return mixed The result of the operation (or cached result)
     * 
     * @throws \App\Application\Common\Exceptions\ConcurrentRequestException If lock is held by another process
     * @throws \App\Application\Common\Exceptions\IdempotencyKeyReuseException If key is reused with different hash
     */
    public function execute(string $idempotencyKey, string $tenantId, string $commandHash, Closure $operation): mixed;
}
