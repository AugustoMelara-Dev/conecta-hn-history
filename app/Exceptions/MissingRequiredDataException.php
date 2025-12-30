<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * MissingRequiredDataException - Thrown when critical DTO fields are missing.
 */
class MissingRequiredDataException extends DataIntegrityException
{
    public static function forField(string $field, string $dto): self
    {
        return new self(
            "Missing required field '{$field}' for {$dto}",
            ['field' => $field, 'dto' => $dto]
        );
    }

    public static function forFields(array $fields, string $dto): self
    {
        return new self(
            "Missing required fields: " . implode(', ', $fields) . " for {$dto}",
            ['fields' => $fields, 'dto' => $dto]
        );
    }
}
