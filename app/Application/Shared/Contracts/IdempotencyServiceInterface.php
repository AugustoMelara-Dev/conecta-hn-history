<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

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
     * @param  string  $key  The idempotency key
     * @return mixed The cached result or null if not found
     */
    public function get(string $key): mixed;

    /**
     * Retrieve the current state of an idempotency key.
     *
     * @return string|null 'PROCESSING', 'COMPLETED', or null if not found
     */
    public function getState(string $key): ?string;

    /**
     * Atomically register a key as PROCESSING.
     * Throws exception if key already exists (in any state).
     *
     * @throws \App\Application\Shared\Common\Exceptions\ConcurrentRequestException
     */
    public function registerProcessing(string $key, int $ttlSeconds = 60): void;

    /**
     * Store an operation result for idempotency replay and mark COMPLETED.
     *
     * @param  string  $key  The idempotency key
     * @param  mixed  $value  The result to store
     * @param  int  $ttlSeconds  Time-to-live in seconds (default 24h)
     */
    public function store(string $key, mixed $value, int $ttlSeconds = 86400): void;
}
