<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * MissingRequiredDataException - Thrown when critical DTO fields are missing.
 */
final class MissingRequiredDataException extends DataIntegrityException
{
    public function __construct(
        string $message,
        array $contextData = [],
        private readonly ?string $entityName = null,
        private readonly array $missingFields = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $contextData, $entityName ?? '', [], $previous);
    }

    public function context(): array
    {
        return [
            'entity' => $this->entityName,
            'missing_fields' => $this->missingFields,
            ...parent::context(),
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 422; // Unprocessable Entity
    }

    public function getErrorCode(): string
    {
        return 'MISSING_REQUIRED_DATA';
    }

    public function entity(): string
    {
        return $this->entityName ?? '';
    }

    public function missingFields(): array
    {
        return $this->missingFields;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Factory Methods
    // ═══════════════════════════════════════════════════════════════════════

    public static function forField(string $field, string $dto): self
    {
        return new self(
            message: "Missing required field '{$field}' for {$dto}",
            contextData: ['field' => $field, 'dto' => $dto],
            entityName: $dto,
            missingFields: [$field],
        );
    }

    public static function forFields(array $fields, string $dto): self
    {
        return new self(
            message: "Missing required fields: " . implode(', ', $fields) . " for {$dto}",
            contextData: ['fields' => $fields, 'dto' => $dto],
            entityName: $dto,
            missingFields: $fields,
        );
    }
}
