<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Shared\Contracts\ResilientRateLimiterInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

final readonly class ResilientRateLimiter implements ResilientRateLimiterInterface
{
    public function __construct(
        private RateLimiter $limiter
    ) {}

    /**
     * Attempts to execute the callback within rate limits.
     * Handles Redis failures based on the $failOpen strategy.
     *
     * @param  string  $key  The rate limit key (e.g., 'upload:tenant:123')
     * @param  int  $maxAttempts  Max attempts allowed in decay period
     * @param  int  $decaySeconds  Decay period in seconds
     * @param  callable  $callback  The logic to execute if allowed
     * @param  bool  $failOpen  Strategy: TRUE = Allow on Redis Fail (Revenue), FALSE = Block on Redis Fail (Security)
     * @return mixed The return value of the callback
     *
     * @throws ValidationException If rate limit exceeded (429)
     * @throws ServiceUnavailableHttpException If Redis fails and $failOpen is FALSE
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds, callable $callback, bool $failOpen = false): mixed
    {
        try {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                $seconds = $this->limiter->availableIn($key);

                throw ValidationException::withMessages([
                    'rate_limit' => ["Too many requests. Please try again in {$seconds} seconds."],
                ]);
            }

            $this->limiter->hit($key, $decaySeconds);

        } catch (ValidationException $e) {
            throw $e; // Always respect legitimate Rate Limits
        } catch (Throwable $e) {
            // INFRASTRUCTURE FAILURE DETECTED (e.g., Redis Connection Refused)

            if ($failOpen) {
                // SCENARIO A: FAIL-OPEN (Revenue Priority)
                Log::critical('ResilientRateLimiter: Infrastructure Failure - BYPASSING LIMITER (Fail-Open Active).', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                // Proceed to execute callback despite limiter failure
            } else {
                // SCENARIO B: FAIL-CLOSED (Security Priority)
                Log::critical('ResilientRateLimiter: Infrastructure Failure - BLOCKING REQUEST (Fail-Closed Active).', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);

                throw new ServiceUnavailableHttpException(
                    null,
                    'Service temporarily unavailable due to security infrastructure outage.'
                );
            }
        }

        // Execute the protected logic
        return $callback();
    }
}
