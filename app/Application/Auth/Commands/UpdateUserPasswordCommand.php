<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * UpdateUserPasswordCommand - Secure Password Change Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDOR PROTECTION: Carries requestingTenantId for ownership validation.  ║
 * ║  SECURITY: Plain password is hashed in Handler, never stored in logs.   ║
 * ║  IMMUTABLE: final readonly prevents tampering after dispatch.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordCommand implements \App\Application\Common\Contracts\HashableCommand
{
    public function __construct(
        /** User ID to update (UUID string) */
        public string $userId,

        /**
         * Tenant ID from the requesting context.
         * CRITICAL: Used to verify ownership before password change.
         * Must match the user's tenantId or operation is aborted.
         */
        public string $requestingTenantId,

        /** Current plain text password (for verification) */
        public string $currentPassword,
        
        /** New plain text password (will be hashed) */
        public string $newPassword,
        
        /** Whether to refresh remember token (forces re-login on other devices) */
        public bool $refreshRememberToken = false,

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
            'newPasswordHash' => md5($this->newPassword), // Avoid logging plain password in hash if debugged
            // We include md5 of new password to discriminate specific change attempts
            // MD5 is fast and "good enough" for identifying if payload changed, we don't store this hash as password.
        ]));
    }
}
