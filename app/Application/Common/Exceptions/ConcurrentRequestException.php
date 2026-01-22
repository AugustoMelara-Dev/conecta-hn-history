<?php

declare(strict_types=1);

namespace App\Application\Common\Exceptions;

use RuntimeException;

class ConcurrentRequestException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("A request with idempotency key '{$key}' is currently processing.");
    }
}
