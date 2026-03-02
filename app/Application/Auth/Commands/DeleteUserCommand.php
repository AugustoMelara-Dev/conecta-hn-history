<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * DeleteUserCommand - Secure User Deletion Intent Contract
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  IDOR PROTECTION: Carries requesting tenant ID for ownership validation.â•‘
 * â•‘  SELF-CONTAINED: No reliance on Auth::user() in Handler.                â•‘
 * â•‘  PRIMITIVES ONLY: No Eloquent models - just scalar types.               â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class DeleteUserCommand implements \App\Application\Shared\Contracts\HashableCommandInterface, \App\Application\Shared\Contracts\VersionedCommandInterface
{
    use \App\Application\Shared\Traits\HasSchemaVersion;

    public function __construct(
        /** User ID to delete (UUID string) */
        public string $userId,

        /**
         * Tenant ID from the requesting context.
         * CRITICAL: Used to verify ownership before deletion.
         */
        public string $requestingTenantId,

        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey,
        public int $schemaVersion = 1,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getTenantId(): string
    {
        return $this->requestingTenantId;
    }

    public function getCommandHash(): string
    {
        return hash('sha256', json_encode([
            'userId' => $this->userId,
            'requestingTenantId' => $this->requestingTenantId,
            'action' => 'DELETE', // Explicit action discriminator
        ]));
    }
}
