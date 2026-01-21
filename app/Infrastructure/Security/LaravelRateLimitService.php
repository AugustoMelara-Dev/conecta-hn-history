<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Shared\Contracts\RateLimitServiceInterface;
use Illuminate\Support\Facades\RateLimiter;

/**
 * LaravelRateLimitService - Infrastructure Adapter for Laravel Rate Limiting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ONLY CLASS that imports RateLimiter Facade.                            ║
 * ║  FIXED: Added hitAndCheck(), fixed hit() return type to int.            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LaravelRateLimitService implements RateLimitServiceInterface
{
    /**
     * {@inheritDoc}
     *
     * ATOMIC: Increments counter and checks in single operation to prevent race conditions.
     */
    public function hitAndCheck(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $attempts = RateLimiter::hit($key, $decaySeconds);

        return $attempts > $maxAttempts;
    }

    /**
     * {@inheritDoc}
     */
    public function checkTooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * {@inheritDoc}
     *
     * FIXED: Now returns int (attempt count) as specified in interface.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * {@inheritDoc}
     */
    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }
}
