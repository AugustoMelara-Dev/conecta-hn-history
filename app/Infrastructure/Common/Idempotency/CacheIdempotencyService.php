<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\Idempotency;

use App\Application\Shared\Common\Exceptions\ConcurrentRequestException;
use App\Application\Shared\Contracts\IdempotencyServiceInterface;
use Illuminate\Support\Facades\Cache;

final class CacheIdempotencyService implements IdempotencyServiceInterface
{
    private const DEFAULT_TTL = 86400; // 24h

    public function get(string $key): mixed
    {
        $envelope = Cache::get($this->formatKey($key));

        if (! is_array($envelope)) {
            return null;
        }

        return $envelope['data'] ?? null;
    }

    public function getState(string $key): ?string
    {
        $envelope = Cache::get($this->formatKey($key));

        if (! is_array($envelope)) {
            return null;
        }

        $state = $envelope['status'] ?? null;

        return is_string($state) ? $state : 'COMPLETED';
    }

    public function registerProcessing(string $key, int $ttlSeconds = 60): void
    {
        $payload = [
            'status' => 'PROCESSING',
            'data' => null,
            'timestamp' => time(),
        ];

        $acquired = Cache::add($this->formatKey($key), $payload, $ttlSeconds);

        if (! $acquired) {
            throw ConcurrentRequestException::forKey($key);
        }
    }

    public function store(string $key, mixed $value, int $ttlSeconds = self::DEFAULT_TTL): void
    {
        Cache::put($this->formatKey($key), [
            'status' => 'COMPLETED',
            'data' => $value,
            'timestamp' => time(),
        ], $ttlSeconds);
    }

    private function formatKey(string $key): string
    {
        return "idempotency:{$key}";
    }
}
