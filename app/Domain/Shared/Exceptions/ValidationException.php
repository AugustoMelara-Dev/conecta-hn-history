<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

/**
 * ValidationException - Domain-Layer Validation Error
 *
 * Thrown when input data fails validation rules.
 * Framework-agnostic alternative to Illuminate\Validation\ValidationException.
 */
class ValidationException extends \RuntimeException
{
    /**
     * @param  string  $message  Human-readable error message
     * @param  array<string, array<int, string>>  $errors  Field-level error messages
     */
    public function __construct(
        string $message = 'The given data was invalid.',
        private readonly array $errors = [],
        int $code = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
