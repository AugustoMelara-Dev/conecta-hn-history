<?php

declare(strict_types=1);

namespace App\Application\Common\Contracts;

/**
 * RateLimiterInterface - Framework-Agnostic Rate Limiting Contract
 *
 * This interface enables DIP compliance by allowing Application layer
 * decorators to depend on an abstraction rather than the concrete
 * Illuminate\Cache\RateLimiter class.
 *
 * ARCHITECTURAL PURPOSE:
 * - Zero framework coupling in the Application layer
 * - Allows switching rate limiter implementations (Redis, DB, in-memory)
 * - Makes unit testing trivial with mock implementations
 */
interface RateLimiterInterface
{
    /**
     * Check if too many attempts have been made for the given key.
     *
     * @param string $key The rate limit key (e.g., "user:123:register")
     * @param int $maxAttempts Maximum allowed attempts within the decay period
     * @return bool True if rate limit exceeded, false otherwise
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for the given key.
     *
     * @param string $key The rate limit key
     * @param int $decaySeconds How long until the counter resets
     * @return int The new number of attempts
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Get the number of seconds until the rate limit resets.
     *
     * @param string $key The rate limit key
     * @return int Seconds remaining before retry is allowed
     */
    public function availableIn(string $key): int;

    /**
     * Clear all rate limit attempts for the given key.
     *
     * @param string $key The rate limit key
     */
    public function clear(string $key): void;
}
