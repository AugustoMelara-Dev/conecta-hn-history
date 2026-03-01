<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Exceptions;

use RuntimeException;

final class IdempotencyKeyReuseException extends RuntimeException
{
    public function __construct(string $message = 'Idempotency key reuse detected', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $previous);
    }
}
