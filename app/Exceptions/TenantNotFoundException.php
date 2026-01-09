<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * TenantNotFoundException - Domain Exception
 *
 * Thrown when a tenant lookup fails in the Application Layer.
 * Replaces Eloquent's ModelNotFoundException for strict DIP compliance.
 */
final class TenantNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Tenant not found')
    {
        parent::__construct($message);
    }
}
