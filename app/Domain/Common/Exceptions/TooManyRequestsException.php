<?php

declare(strict_types=1);

namespace App\Domain\Common\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class TooManyRequestsException extends Exception
{
    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Please try again later.',
        ], 429, [
            'Retry-After' => 60,
        ]);
    }
}
