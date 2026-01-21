<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * IdempotencyStoreInterface
 *
 * Abstraction for atomic locking mechanism.
 * Implementations MUST propagate infrastructure exceptions so the
 * Middleware can decide whether to Fail-Open or Fail-Secure.
 */
interface IdempotencyStoreInterface
{
    /**
     * Attempt to acquire an atomic lock for the given key.
     *
     * @param  string  $key  Unique idempotency key
     * @param  int  $ttlSeconds  Time to live in seconds
     * @return bool True if lock acquired, False if already locked.
     *
     * @throws \Throwable If infrastructure fails (Redis down, etc).
     */
    public function acquire(string $key, int $ttlSeconds): bool;

    /**
     * Release the lock (optional, mostly for testing or rollback).
     */
    public function release(string $key): void;
}
