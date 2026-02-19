<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Exceptions;

use RuntimeException;

class IdempotencyKeyReuseException extends RuntimeException
{
    protected $code = 409; // Conflict
}
