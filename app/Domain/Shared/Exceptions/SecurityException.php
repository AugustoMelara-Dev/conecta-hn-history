<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use Exception;

final class SecurityException extends Exception
{
    public static function crossTenantAccess(string $resourceId, string $requestingTenantId): self
    {
        return new self("Security Violation: Access to resource {$resourceId} denied from tenant {$requestingTenantId}.");
    }
}
