<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exceptions;

use DomainException;

/**
 * UserNotFoundException - Domain-Specific Not Found Exception
 *
 * Decoupled from Illuminate\Database\Eloquent\ModelNotFoundException
 * to maintain hexagonal architecture boundaries.
 *
 * SECURITY: Caution with message verbosity. Ensure logs are sanitized
 * via LogSanitizer before storage.
 */
final class UserNotFoundException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $userId,
        private readonly ?string $tenantId = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Factory: User not found by ID alone.
     */
    public static function withId(string $userId): self
    {
        return new self(
            message: "User with ID [{$userId}] not found.",
            userId: $userId,
        );
    }

    /**
     * Factory: User not found within tenant context.
     *
     * Standardized semantic message for debugging clarity.
     */
    public static function withIdAndTenant(string $userId, string $tenantId): self
    {
        return new self(
            message: "User with ID [{$userId}] not found in tenant [{$tenantId}] context.",
            userId: $userId,
            tenantId: $tenantId,
        );
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }
}
