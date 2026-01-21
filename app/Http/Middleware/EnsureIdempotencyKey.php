<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Shared\Context\IdempotencyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotencyKey
{
    public function __construct(
        private IdempotencyContext $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce on mutation methods
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $key = $request->header('Idempotency-Key');

            if (empty($key)) {
                return response()->json([
                    'error' => 'Missing Idempotency-Key header',
                    'message' => 'This operation requires an Idempotency-Key header for safe retries.',
                ], 400);
            }

            // Hydrate Context
            $this->context->setKey((string) $key);
        }

        return $next($request);
    }
}
