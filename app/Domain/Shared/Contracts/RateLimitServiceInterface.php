<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * RateLimitServiceInterface - Domain Port for Rate Limiting
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ABSTRACTION: Decouples business logic from Laravel's RateLimiter.      ║
 * ║  TESTABLE: Allows mocking in unit tests.                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface RateLimitServiceInterface
{
    /**
     * Check if too many attempts have been made.
     *
     * @param string $key Unique identifier (e.g., "otp_gen:user_email@test.com")
     * @param int $maxAttempts Maximum allowed attempts
     * @return bool True if limit exceeded (BLOCKED)
     */
    public function checkTooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Record an attempt (increment counter).
     *
     * @param string $key Unique identifier
     * @param int $decaySeconds How long until counter resets (default 60s)
     */
    public function hit(string $key, int $decaySeconds = 60): void;

    /**
     * Clear all attempts for a key (reset counter).
     *
     * @param string $key Unique identifier
     */
    public function clear(string $key): void;

    /**
     * Get seconds until rate limit resets.
     *
     * @param string $key Unique identifier
     * @return int Seconds remaining (0 if not limited)
     */
    public function availableIn(string $key): int;
}
