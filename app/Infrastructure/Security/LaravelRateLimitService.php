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
 * ║  Maps domain contract to Laravel's rate limiting infrastructure.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LaravelRateLimitService implements RateLimitServiceInterface
{
    /**
     * @inheritDoc
     */
    public function checkTooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * @inheritDoc
     */
    public function hit(string $key, int $decaySeconds = 60): void
    {
        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * @inheritDoc
     */
    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * @inheritDoc
     */
    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }
}
