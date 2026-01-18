<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * RedisIdempotencyService - Distributed Idempotency with Redis Backend
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: This service is the last defense against duplicate payments. ║
 * ║  INTERFACE COMPLIANCE: Strictly implements IdempotencyServiceInterface. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RedisIdempotencyService implements IdempotencyServiceInterface
{
    private const DEFAULT_TTL = 86400; // 24 hours

    public function __construct(
        private readonly Connection $redis
    ) {}

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->formatKey($key));

        if ($value === null) {
            return null;
        }

        return unserialize($value);
    }

    /**
     * {@inheritdoc}
     * 
     * FIXED: Added $ttlSeconds parameter to match interface contract.
     */
    public function store(string $key, mixed $value, int $ttlSeconds = self::DEFAULT_TTL): void
    {
        $serialized = serialize($value);
        
        $this->redis->setex(
            $this->formatKey($key),
            $ttlSeconds,
            $serialized
        );
    }

    /**
     * {@inheritdoc}
     *
     * FIXED: Renamed from lock() to atomic() and updated signature to match interface.
     * Uses Laravel's Cache lock for atomic execution.
     */
    public function atomic(string $key, int $seconds, callable $callback): mixed
    {
        $lock = Cache::lock($this->formatKey($key), $seconds);

        return $lock->block($seconds, function () use ($callback) {
            return $callback();
        });
    }

    private function formatKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
