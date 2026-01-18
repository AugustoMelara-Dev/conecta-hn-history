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
final readonly class DeleteUserCommand
{
    public function __construct(
        /** User ID to delete (UUID string) */
        public string $userId,
        
        /** 
         * Tenant ID from the requesting context.
         * CRITICAL: Used to verify ownership before deletion.
         */
        public string $requestingTenantId,
    ) {}
}
