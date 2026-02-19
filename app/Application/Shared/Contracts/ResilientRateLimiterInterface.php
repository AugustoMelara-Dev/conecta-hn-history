<?php

declare(strict_types=1);

namespace App\Application\Shared\Contracts;

/**
 * ResilientRateLimiterInterface - Fail-Strategy Rate Limiting Contract
 *
 * Higher-level rate limiting contract that encapsulates the attempt lifecycle
 * (check → hit → execute) with configurable fail-open/fail-closed strategies.
 * The Application Layer uses this contract; the Infrastructure Layer provides
 * the implementation backed by Redis, database, or in-memory stores.
 */
interface ResilientRateLimiterInterface
{
    /**
     * Attempts to execute the callback within rate limits.
     *
     * @param  string  $key  The rate limit key (e.g., 'upload:tenant:123')
     * @param  int  $maxAttempts  Max attempts allowed in decay period
     * @param  int  $decaySeconds  Decay period in seconds
     * @param  callable  $callback  The logic to execute if allowed
     * @param  bool  $failOpen  Strategy: TRUE = Allow on infra fail, FALSE = Block on infra fail
     * @return mixed The return value of the callback
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds, callable $callback, bool $failOpen = false): mixed;
}
