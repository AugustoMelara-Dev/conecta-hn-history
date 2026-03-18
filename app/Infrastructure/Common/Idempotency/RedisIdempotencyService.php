<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * RedisIdempotencyService - Distributed Idempotency with Redis Backend
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  CRITICAL: This service is the last defense against duplicate payments. â•‘
 * â•‘  INTERFACE COMPLIANCE: Strictly implements IdempotencyServiceInterface. â•‘
 * â•‘  PROTOCOL: Uses {status, data} envelope for state management.           â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
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

        $data = unserialize($value);

        // Unwrap envelope if present
        if (is_array($data) && isset($data['status'])) {
            return $data['data'] ?? null;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(string $key): ?string
    {
        $value = $this->redis->get($this->formatKey($key));

        if ($value === null) {
            return null;
        }

        $data = unserialize($value);

        if (is_array($data) && isset($data['status'])) {
            return $data['status'];
        }

        // Legacy compatibility: If value exists but no status, assume COMPLETED
        return 'COMPLETED';
    }

    /**
     * {@inheritdoc}
     */
    public function registerProcessing(string $key, int $ttlSeconds = 60): void
    {
        $payload = serialize([
            'status' => 'PROCESSING',
            'data' => null,
            'timestamp' => time(),
        ]);

        // SETNX: Set if Not Exists
        // Returns 1 if set, 0 if not set (already exists)
        $acquired = $this->redis->setnx($this->formatKey($key), $payload);

        if (! $acquired) {
            // Key exists. It might be PROCESSING or COMPLETED.
            throw ConcurrentRequestException::forKey($key);
        }

        // Set expiry for the processing lock
        $this->redis->expire($this->formatKey($key), $ttlSeconds);
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $key, mixed $value, int $ttlSeconds = self::DEFAULT_TTL): void
    {
        $payload = serialize([
            'status' => 'COMPLETED',
            'data' => $value,
            'timestamp' => time(),
        ]);

        $this->redis->setex(
            $this->formatKey($key),
            $ttlSeconds,
            $payload
        );
    }

    /**
     * Exposes a framework-backed lock for atomic decorator execution.
     */
    public function lock(string $key, int $ttlSeconds = 60): Lock
    {
        return Cache::lock($this->formatKey($key).':lock', $ttlSeconds);
    }

    private function formatKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
