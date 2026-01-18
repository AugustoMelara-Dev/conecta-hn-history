<?php

declare(strict_types=1);

namespace App\Application\User\Commands;

/**
 * UpdateUserProfileCommand - Secure Profile Update Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IDOR PROTECTION: Carries requestingTenantId for ownership validation.  ║
 * ║  EMAIL CHANGE: If email changes, verification is reset automatically.   ║
 * ║  IMMUTABLE: final readonly prevents tampering after dispatch.           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserProfileCommand
{
    public function __construct(
        /** User ID to update (UUID string) */
        public string $userId,

        /**
         * Tenant ID from the requesting context.
         * CRITICAL: Used to verify ownership before profile update.
         * Must match the user's tenantId or operation is aborted.
         */
        public string $requestingTenantId,
        
        /** New display name */
        public string $name,
        
        /** New email address */
        public string $email,
    ) {}
}
