<?php

declare(strict_types=1);

namespace App\Infrastructure\Exceptions;

use RuntimeException;

/**
 * InfrastructureACIDException - Level 52 Zenith True
 *
 * Thrown when a fundamental ACID guarantee of the infrastructure is violated.
 * Example: Read-Your-Own-Writes (RYOW) failure where an atomic UPDATE
 * claims N rows but the subsequent pinned fetch returns < N rows.
 */
final class InfrastructureACIDException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
