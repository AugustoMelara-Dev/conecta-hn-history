<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

final class TenantContextMutationException extends RuntimeException
{
    public static function tenantHopping(int $currentId, int $newId): self
    {
        return new self("Security Violation: Attempted to switch Tenant Context from {$currentId} to {$newId} in the same request (Tenant Hopping).");
    }

    public static function alreadyInitialized(): self
    {
        return new self("Security Violation: Context already initialized. Cannot overwrite.");
    }
}
