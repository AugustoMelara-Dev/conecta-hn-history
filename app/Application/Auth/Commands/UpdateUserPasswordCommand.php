<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

use App\Application\Shared\Contracts\SensitiveDataInterface;

/**
 * UpdateUserPasswordCommand - Secure Password Change Contract
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  IDOR PROTECTION: Carries requestingTenantId for ownership validation.  â•‘
 * â•‘  SEC-01 FIX: Passwords scrubbed from stack traces via SensitiveParameterâ•‘
 * â•‘  IMMUTABLE: final readonly prevents tampering after dispatch.           â•‘
 * â•‘  P1-REDACT: SensitiveDataInterface for Outbox/log sanitization.        â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class UpdateUserPasswordCommand implements \App\Application\Shared\Contracts\HashableCommandInterface, SensitiveDataInterface
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

        /** Current password for verification (scrubbed from stack traces) */
        #[\SensitiveParameter] public string $currentPassword,

        /** New password (scrubbed from stack traces, hashed by Handler) */
        #[\SensitiveParameter] public string $newPassword,

        /** Whether to refresh remember token (forces re-login on other devices) */
        public bool $refreshRememberToken,

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
        // SEC-01 FIX: No password derivatives in command hash.
        // Discriminate by user + idempotency key only.
        return hash('xxh128', 'update_pwd_' . $this->userId . '_' . $this->idempotencyKey);
    }

    public function getSensitiveProperties(): array
    {
        return ['currentPassword', 'newPassword'];
    }
}
