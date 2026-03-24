<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Cache;

/**
 * RedisIdempotencyService - Distributed idempotency with Redis.
 *
 * This service is part of the last line of defense against duplicate
 * processing. It stores an envelope with {status, data} so requests can move
 * safely between PROCESSING and COMPLETED states.
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

        // Legacy compatibility: if the value exists but no status is stored,
        // treat it as a completed request.
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

        $acquired = $this->redis->setnx($this->formatKey($key), $payload);

        if (! $acquired) {
            throw ConcurrentRequestException::forKey($key);
        }

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
