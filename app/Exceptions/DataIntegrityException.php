<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * DataIntegrityException - Thrown when data violates integrity constraints.
 *
 * Covers: duplicate entries, FK violations, constraint failures.
 */
class DataIntegrityException extends DomainException
{
    public function __construct(
        string $message,
        private readonly array $contextData = [],
        private readonly string $entity = '',
        private readonly array $violatedConstraints = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->validateContextSafety($contextData);
    }

    public function context(): array
    {
        return [
            'entity' => $this->entity,
            'violated_constraints' => $this->violatedConstraints,
            ...$this->contextData,
        ];
    }

    public function getHttpStatusCode(): int
    {
        return 409; // Conflict
    }

    public function getErrorCode(): string
    {
        return 'DATA_INTEGRITY_VIOLATION';
    }

    public function entity(): string
    {
        return $this->entity;
    }

    public function violatedConstraints(): array
    {
        return $this->violatedConstraints;
    }

    /**
     * Factory: Create for duplicate entry violation.
     */
    public static function duplicateEntry(string $entity, string $field, mixed $value): self
    {
        return new self(
            message: "Duplicate {$field} for {$entity}",
            contextData: [$field => $value],
            entity: $entity,
            violatedConstraints: ["{$field}_unique"],
        );
    }
}
