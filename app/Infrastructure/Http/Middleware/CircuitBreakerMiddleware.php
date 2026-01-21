<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class CircuitBreakerMiddleware
{
    private const MAX_FAILURES = 5;

    private const DECAY_SECONDS = 60;

    private const CACHE_KEY_OPEN = 'circuit:open';

    private const CACHE_KEY_FAILURES = 'circuit:failures';

    public function handle(Request $request, Closure $next): Response
    {
        // 1. FAIL-FAST CHECK (The Pre-Flight)
        if (Cache::has(self::CACHE_KEY_OPEN)) {
            // "The Fail-Fast Law": Reject immediately without touching DB
            abort(503, 'System is recovering, please try again later');
        }

        try {
            // 2. EXECUTION SHIELD
            $response = $next($request);

            // OPTIONAL: Reset failures on success ("Forgiveness Protocol")
            // Ideally we let them expire, but for strictness we might want to keep the count
            // until it naturally decays to avoid flapping. Leaving as is (decay) for now.

            return $response;

        } catch (QueryException $e) {
            // 3. FAILURE HANDLING
            $failures = Cache::increment(self::CACHE_KEY_FAILURES);

            // 4. CIRCUIT OPENING LOGIC
            if ($failures >= self::MAX_FAILURES) {
                // Open the circuit for DECAY_SECONDS
                Cache::put(self::CACHE_KEY_OPEN, true, self::DECAY_SECONDS);

                // Reset failure count so we start fresh after recovery
                Cache::forget(self::CACHE_KEY_FAILURES);
            }

            // Re-throw to let the framework handle the error response,
            // but the NEXT request will be blocked.
            throw $e;
        }
    }
}
