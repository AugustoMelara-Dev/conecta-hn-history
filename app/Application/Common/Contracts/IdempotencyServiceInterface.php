<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

/**
 * IdempotencyServiceInterface - Framework-Agnostic Distributed State Manager
 *
 * CRITICAL FIX: Removed Illuminate\Contracts\Cache\Lock dependency.
 * Now uses a generic callback approach for atomic operations.
 *
 * This interface enables DIP compliance by allowing Application layer
 * decorators to depend on an abstraction rather than any framework-specific
 * implementation.
 *
 * ARCHITECTURAL PURPOSE:
 * - Zero framework coupling in the interface
 * - Allows IdempotentRegisterUserHandler to remain pure
 * - Makes unit testing trivial with mock implementations
 */
interface IdempotencyServiceInterface
{
    /**
     * Retrieve a previously stored result by idempotency key.
     *
     * @param string $key The idempotency key
     * @return mixed The cached result or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Store an operation result for idempotency replay.
     *
     * @param string $key The idempotency key
     * @param mixed $value The result to store
     * @param int $ttlSeconds Time-to-live in seconds (default 24h)
     */
    public function store(string $key, mixed $value, int $ttlSeconds = 86400): void;

    /**
     * Execute a callback atomically with distributed locking.
     *
     * This method provides a framework-agnostic way to acquire a lock
     * and execute critical sections without exposing Lock objects.
     *
     * @param string $key The lock key
     * @param int $seconds Maximum time to wait for lock acquisition
     * @param callable $callback The operation to execute atomically
     * @return mixed The result of the callback
     */
    public function atomic(string $key, int $seconds, callable $callback): mixed;
}
