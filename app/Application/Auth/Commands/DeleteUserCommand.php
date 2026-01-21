<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * DeleteUserCommand - Secure User Deletion Intent Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDOR PROTECTION: Carries requesting tenant ID for ownership validation.║
 * ║  SELF-CONTAINED: No reliance on Auth::user() in Handler.                ║
 * ║  PRIMITIVES ONLY: No Eloquent models - just scalar types.               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class DeleteUserCommand implements \App\Application\Common\Contracts\HashableCommandInterface
{
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
