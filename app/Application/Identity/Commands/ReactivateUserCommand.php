<?php

declare(strict_types=1);

namespace App\Application\Identity\Commands;

/**
 * ReactivateUserCommand - Immutable Command for User Reactivation
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  IMMUTABLE: final readonly prevents tampering after dispatch.           ║
 * ║  TYPE SAFE: All identifiers are string (UUID/ULID compatible).         ║
 * ║  TENANT AWARE: Carries tenantId for ownership validation.              ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class ReactivateUserCommand
{
    public function __construct(
        /** UUID of the user to reactivate */
        public string $userId,

        /** UUID of the requesting tenant context */
        public string $tenantId,
    ) {}
}
