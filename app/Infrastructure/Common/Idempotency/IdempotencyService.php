<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Lock;
use RuntimeException;

/**
 * IdempotencyService - Distributed State Manager
 * 
 * Safe serialization using JSON to prevent PHP Class Mismatch errors.
 */
final readonly class IdempotencyService
{
    public function __construct(
        private CacheRepository $cache
    ) {}

    public function get(string $key): ?object
    {
        $json = $this->cache->get("idempotency_result:{$key}");

        if (! $json) {
            return null;
        }

        $payload = json_decode($json, true);

        if (! isset($payload['class'], $payload['data'])) {
            return null;
        }

        $className = $payload['class'];

        if (! class_exists($className) || ! method_exists($className, 'fromArray')) {
             return null;
        }

        return $className::fromArray($payload['data']);
    }

    public function store(string $key, object $result, int $ttlSeconds = 86400): void
    {
        if (! method_exists($result, 'toArray')) {
            throw new RuntimeException("Result object " . get_class($result) . " must implement toArray() for safe serialization.");
        }

        $payload = [
            'class' => get_class($result),
            'data' => $result->toArray(),
        ];

        $this->cache->put("idempotency_result:{$key}", json_encode($payload), $ttlSeconds);
    }

    public function lock(string $key): Lock
    {
        // Atomic lock with 10s expiration to prevent deadlocks
        return $this->cache->lock("idempotency_lock:{$key}", 10);
    }
}
