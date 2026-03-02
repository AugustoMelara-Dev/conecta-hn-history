<?php

declare(strict_types=1);

namespace App\Application\Shared\Common\Exceptions;

use RuntimeException;

final class RateLimitExceededException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Rate limit exceeded for key: {$key}");
    }
}
