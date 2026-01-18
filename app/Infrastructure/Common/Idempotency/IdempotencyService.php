<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

/**
 * IdempotencyService - Laravel Cache-Based Implementation
 *
 * Implements the framework-agnostic IdempotencyServiceInterface
 * using Laravel's cache repository.
 *
 * Safe serialization using JSON to prevent PHP Class Mismatch errors.
 */
final readonly class IdempotencyService implements IdempotencyServiceInterface
{
    public function __construct(
        private CacheRepository $cache
    ) {}

    public function get(string $key): mixed
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

    public function store(string $key, mixed $value, int $ttlSeconds = 86400): void
    {
        if (is_object($value) && ! method_exists($value, 'toArray')) {
            throw new RuntimeException("Result object " . get_class($value) . " must implement toArray() for safe serialization.");
        }

        $payload = [
            'class' => is_object($value) ? get_class($value) : null,
            'data' => is_object($value) ? $value->toArray() : $value,
        ];

        $this->cache->put("idempotency_result:{$key}", json_encode($payload), $ttlSeconds);
    }

    public function atomic(string $key, int $seconds, callable $callback): mixed
    {
        // Acquire atomic lock with configurable expiration
        $lock = $this->cache->lock("idempotency_lock:{$key}", $seconds + 5);

        // Block and execute atomically
        return $lock->block($seconds, $callback);
    }
}
