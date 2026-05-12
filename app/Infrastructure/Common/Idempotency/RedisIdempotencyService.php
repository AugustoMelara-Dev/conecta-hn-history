<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Common\Contracts\IdempotencyServiceInterface;
use App\Application\Common\Exceptions\ConcurrentRequestException;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Redis\Connections\Connection;

/**
 * RedisIdempotencyService - Distributed Idempotency with Redis Backend
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: This service is the last defense against duplicate payments. ║
 * ║  INTERFACE COMPLIANCE: Strictly implements IdempotencyServiceInterface. ║
 * ║  PROTOCOL: Uses {status, data} envelope for state management.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class RedisIdempotencyService implements IdempotencyServiceInterface
{
    private const DEFAULT_TTL = 86400; // 24 hours

    public function __construct(
        private readonly Connection|CacheRepository $store
    ) {}

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $value = $this->getRaw($key);

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
        $value = $this->getRaw($key);

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

        $formattedKey = $this->formatKey($key);
        $acquired = $this->store instanceof Connection
            ? (bool) $this->store->setnx($formattedKey, $payload)
            : $this->store->add($formattedKey, $payload, $ttlSeconds);

        if (! $acquired) {
            // Key exists. It might be PROCESSING or COMPLETED.
            throw ConcurrentRequestException::forKey($key);
        }

        if ($this->store instanceof Connection) {
            $this->store->expire($formattedKey, $ttlSeconds);
        }
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

        if ($this->store instanceof Connection) {
            $this->store->setex($this->formatKey($key), $ttlSeconds, $payload);

            return;
        }

        $this->store->put($this->formatKey($key), $payload, $ttlSeconds);
    }

    private function getRaw(string $key): mixed
    {
        return $this->store->get($this->formatKey($key));
    }

    private function formatKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
