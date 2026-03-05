<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Exceptions;

use RuntimeException;

final class ConcurrentRequestException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("A request with idempotency key '{$key}' is currently processing.");
    }
}
