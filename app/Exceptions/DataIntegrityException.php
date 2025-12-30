<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * DataIntegrityException - Base exception for DTO validation failures.
 *
 * All DTO-level validation exceptions should extend this class
 * to enable centralized error handling.
 */
class DataIntegrityException extends \DomainException
{
    protected array $context = [];

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
