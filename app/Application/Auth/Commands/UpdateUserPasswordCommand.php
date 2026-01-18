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
final readonly class UpdateUserPasswordCommand
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
    ) {}
}
