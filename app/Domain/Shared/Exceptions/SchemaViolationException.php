<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

class SchemaViolationException extends RuntimeException
{
    public static function forSubject(string $subject, string $errors): self
    {
        return new self("Schema violation for [{$subject}]: {$errors}");
    }
}
