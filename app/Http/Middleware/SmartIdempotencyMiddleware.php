<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Contracts\IdempotencyLedgerInterface;
use App\Domain\Shared\Contracts\IdempotencyStoreInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SmartIdempotencyMiddleware - State-Based Concurrency Control (Level 30)
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURE: ATOMIC TRUTH CHECK                                        ║
 * ║  1. Fast Lock (Redis): Optimistic Guard (60s).                           ║
 * ║  2. Truth Check (DB): If Lock Fails | Expired -> Check DB.               ║
 * ║  3. Recovery: If DB Has Record -> Return Success (Immediate).            ║
 * ║  4. Gatekeeper: If Lock Held AND DB Empty -> 429 (Processing).           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SmartIdempotencyMiddleware
{
    private const PROCESSING_LOCK_TTL = 60; // Short TTL (Guard, not Storage)

    public function __construct(
        private IdempotencyStoreInterface $lockStore
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $ledgerService  Class-string of IdempotencyLedgerInterface
     */
    public function handle(Request $request, Closure $next, string $ledgerService): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $lockKey = 'idempotency_lock:'.$key;

        // STEP 1: FAST LOCK (Redis)
        // If we get the lock, we are the FIRST (or the previous lock expired).
        if ($this->lockStore->acquire($lockKey, self::PROCESSING_LOCK_TTL)) {

            // CRITICAL: Even if we got the lock, the previous owner might have FINISHED
            // just as the lock expired. We must check the DB to avoid Double Billing.
            // (The "Slope" Scenario: T+61s).

            /** @var IdempotencyLedgerInterface $ledger */
            $ledger = app($ledgerService);

            if ($ledger->hasProcessed($request->input('tenant_id') ?? '', $key)) {
                // RECOVERY: It was already done. The lock expire was a fluke.
                // Release the new lock (we don't need it) and return Success.
                $this->lockStore->release($lockKey);

                // Return a generic "Already Processed" or ideally the original response.
                // Since we don't cache the full response body in this architecture (DB is Truth),
                // we return a 200 OK. The Client logic should be able to fetch the entity.
                return response()->json([
                    'message' => 'Operation already processed.',
                    'idempotency_key' => $key,
                ], 200);
            }

            // DB Empty + Lock Acquired = True First Execution
            try {
                $response = $next($request);
            } catch (\Throwable $e) {
                // If it crashed, release lock so user can retry immediately
                $this->lockStore->release($lockKey);
                throw $e;
            }

            // We do NOT release the lock on success immediately?
            // User says "Short-Term Lock (60s)".
            // If we release it, and the user retries instantly, they hit the DB check.
            // It is safer to RELEASE it now that DB is committed.
            $this->lockStore->release($lockKey);

            return $response;
        }

        // STEP 2: LOCK FAILED (Someone else holds it, OR Redis is down/busy)
        // Check the TRUTH.
        /** @var IdempotencyLedgerInterface $ledger */
        $ledger = app($ledgerService);

        if ($ledger->hasProcessed($request->input('tenant_id') ?? '', $key)) {
            // RECOVERY: It is done. Return Success.
            return response()->json([
                'message' => 'Operation already processed.',
                'idempotency_key' => $key,
            ], 200);
        }

        // STEP 3: GATEKEEPER
        // DB Empty + Lock Held by Others = Active Processing.
        return response()->json([
            'message' => 'Request is currently being processed. Please wait.',
            'retry_after' => 5,
        ], 429);
    }
}
