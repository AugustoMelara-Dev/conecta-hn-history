<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Contracts\IdempotencyStoreInterface;
use Illuminate\Support\Facades\Cache;

final readonly class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    public function acquire(string $key, int $ttlSeconds): bool
    {
        // We use the Atomic Lock feature of Laravel Cache (Redis driver)
        // 'add' returns true if the key ID NOT exist, false if it does.
        // It's atomic (NX).

        // Note: We use 'add' logic manually or via lock.
        // The standard Cache::add() is atomic on Redis.

        // If Redis is down, Cache::add() throws an exception (depending on config).
        // We ensure we don't catch it here, propagating to Middleware.

        return Cache::add("idempotency:{$key}", 'locked', $ttlSeconds);
    }

    public function release(string $key): void
    {
        Cache::forget("idempotency:{$key}");
    }
}
