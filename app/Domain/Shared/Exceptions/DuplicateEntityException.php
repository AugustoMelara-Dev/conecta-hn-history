<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;
use Throwable;

/**
 * DuplicateEntityException
 *
 * Thrown when a persistence operation fails due to a uniqueness constraint violation.
 * This decouples the Application Layer from SQL error codes (e.g., MySQL 1062).
 */
final class DuplicateEntityException extends DomainException
{
    public function __construct(string $message = "Entity already exists.", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
