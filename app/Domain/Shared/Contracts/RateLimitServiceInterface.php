<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * RateLimitServiceInterface - Atomic Rate Limiting Port
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ATOMIC: hitAndCheck combines increment+check to prevent race conditions║
 * ║  ABSTRACTION: Decouples business logic from Laravel's RateLimiter.      ║
 * ║  TESTABLE: Allows mocking in unit tests.                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface RateLimitServiceInterface
{
    /**
     * ATOMIC: Increment counter and check if limit exceeded in single operation.
     *
     * This prevents race conditions where concurrent requests all pass the check
     * before any of them increment the counter.
     *
     * @param  string  $key  Unique identifier (e.g., "otp_gen:tenant:user@test.com")
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @param  int  $decaySeconds  How long until counter resets
     * @return bool True if limit exceeded AFTER this attempt (BLOCKED)
     */
    public function hitAndCheck(string $key, int $maxAttempts, int $decaySeconds = 60): bool;

    /**
     * Check if too many attempts have been made (read-only check).
     *
     * @param  string  $key  Unique identifier
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @return bool True if limit exceeded (BLOCKED)
     */
    public function checkTooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Record an attempt (increment counter).
     *
     * @deprecated Use hitAndCheck() for atomic operations
     *
     * @param  string  $key  Unique identifier
     * @param  int  $decaySeconds  How long until counter resets (default 60s)
     * @return int Current attempt count after increment
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Clear all attempts for a key (reset counter).
     *
     * @param  string  $key  Unique identifier
     */
    public function clear(string $key): void;

    /**
     * Get seconds until rate limit resets.
     *
     * @param  string  $key  Unique identifier
     * @return int Seconds remaining (0 if not limited)
     */
    public function availableIn(string $key): int;
}
