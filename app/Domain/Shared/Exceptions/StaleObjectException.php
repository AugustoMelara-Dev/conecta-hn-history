<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * StaleObjectException - Concurrency Conflict Signal
 *
 * Thrown when an Optimistic Update fails because the object's version
 * in the database does not match the version held in memory.
 */
final class StaleObjectException extends RuntimeException
{
    public static function forEntity(string $entityName, string $id): self
    {
        return new self("Concurrent modification detected on {$entityName} with ID {$id}. The state has changed since it was read.");
    }
}
