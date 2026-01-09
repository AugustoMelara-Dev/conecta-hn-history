<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * UpdateUserPasswordCommand - Immutable Input Contract for Password Change
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SECURITY: Plain password is hashed in Handler, never stored in logs.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserPasswordCommand
{
    public function __construct(
        /** User ID to update */
        public int $userId,

        /** Current plain text password (for verification) */
        public string $currentPassword,
        
        /** New plain text password (will be hashed) */
        public string $newPassword,
        
        /** Whether to refresh remember token (forces re-login on other devices) */
        public bool $refreshRememberToken = false,
    ) {}
}
