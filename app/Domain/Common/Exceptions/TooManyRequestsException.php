<?php

declare(strict_types=1);

namespace App\Domain\Common\Exceptions;

use Exception;

// use Illuminate\Http\JsonResponse; // REMOVED: Domain layer cannot depend on Http layer

final class TooManyRequestsException extends Exception
{
    public function __construct(
        string $message = 'Rate limit exceeded. Please try again later.',
        public readonly int $retryAfterSeconds = 60,
    ) {
        parent::__construct($message);
    }
}
