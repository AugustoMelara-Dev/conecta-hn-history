<?php

declare(strict_types=1);

namespace App\Infrastructure\Common\RateLimiting;

use App\Application\Common\Contracts\RateLimiterInterface;
use Illuminate\Cache\RateLimiter;

/**
 * LaravelRateLimiterAdapter - Framework Adapter
 *
 * Implements the framework-agnostic RateLimiterInterface
 * using Laravel's RateLimiter as the underlying implementation.
 *
 * This adapter allows the Application layer to remain pure
 * while still leveraging Laravel's battle-tested rate limiting.
 */
final readonly class LaravelRateLimiterAdapter implements RateLimiterInterface
{
    public function __construct(
        private RateLimiter $limiter
    ) {}

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->limiter->tooManyAttempts($key, $maxAttempts);
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->limiter->hit($key, $decaySeconds);
    }

    public function availableIn(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }
}
