<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Middleware;

use App\Domain\Shared\Contracts\IdempotencyStoreInterface;
use App\Application\Shared\Contracts\IdempotentCommandInterface;
use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * IdempotencyMiddleware - Concurrency Guard (Level 26 - Smart Lock)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  STRATEGY: SHORT-TERM LOCK + LONG-TERM DB CONSTRAINT                     ║
 * ║  1. Redis Lock TTL = 60s (Processing Window).                            ║
 * ║     - Prevents parallel execution (Race Conditions).                     ║
 * ║  2. DB Unique Index = Permanent (Data Integrity).                        ║
 * ║     - Prevents double-billing forever.                                   ║
 * ║                                                                          ║
 * ║  SOLVES "LOST RECEIPT":                                                  ║
 * ║  If user retries after 60s, Lock is gone. Handler runs.                  ║
 * ║  Handler hits DB Unique Index -> "Recover" logic runs -> RETURN RECEIPT. ║
 * ║  (Legacy 24h TTL blocked this recovery, causing 409 forever).            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class IdempotencyMiddleware
{
    // PROCESSING LOCK ONLY.
    // Allow Handler to run "Recover" logic after this window.
    private const PROCESSING_LOCK_TTL = 60;

    public function __construct(
        private IdempotencyStoreInterface $store,
    ) {}

    public function handle(object $command, Closure $next): mixed
    {
        // 1. INSPECTION: Skip if not idempotent
        if (! ($command instanceof IdempotentCommandInterface)) {
            return $next($command);
        }

        $key = $command->getIdempotencyKey();

        try {
            // 2. VERIFICATION: Attempt Atomic Lock Acquisition
            $acquired = $this->store->acquire($key, self::PROCESSING_LOCK_TTL);

            if (! $acquired) {
                // BRANCH A: CONCURRENCY CONFLICT
                // Another request is processing RIGHT NOW (or within last 60s).
                // Block to prevent race condition.
                // Client should "Retry-After: 60".

                Log::warning('Concurrent request blocked by Idempotency Guard', [
                    'key' => $key,
                    'command' => get_class($command),
                ]);

                throw new RuntimeException('Request is currently being processed. Please retry in a moment.', 429);
            }

        } catch (Throwable $e) {
            // BRANCH B: INFRASTRUCTURE FAILURE (Redis Down)
            // PROTOCOL: FAIL-OPEN (BYPASS)

            if ($e->getCode() === 429) {
                throw $e;
            }

            Log::critical('CRITICAL: Redis Idempotency Store DOWN. Bypassing Guard to Maintain Availability.', [
                'error' => $e->getMessage(),
                'key' => $key,
            ]);

            return $next($command);
        }

        // BRANCH C: SUCCESS (Primary Execution)
        // If this finishes, Lock remains for 60s.
        // If user retries immediately -> 429.
        // If user retries after 60s -> 200 (Recovered from DB).
        return $next($command);
    }
}
