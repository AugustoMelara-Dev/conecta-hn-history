<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

final class TenantContextMissingException extends RuntimeException
{
    public static function uninitialized(): self
    {
        return new self('Critical Error: Tenant Context not initialized. Protected resource access denied (Zero Trust).');
    }
}
