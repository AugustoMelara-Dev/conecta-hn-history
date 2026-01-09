<?php

declare(strict_types=1);

namespace App\Application\Identity\Commands;

/**
 * RegisterUserCommand - Immutable Input Contract for User Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TRANSPORT ONLY: No validation logic, no framework dependencies.        ║
 * ║  SERIALIZABLE: Safe for queue dispatch if needed.                       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RegisterUserCommand
{
    public function __construct(
        /** User's full name */
        public string $name,
        
        /** User's email address (validated at controller level) */
        public string $email,
        
        /** Plain text password (hashing is Handler's responsibility) */
        public string $password,
        
        /** Optional tenant/organization name for implicit tenant creation */
        public ?string $tenantName = null,
        
        /** Optional slug for the tenant */
        public ?string $slug = null,
    ) {}
}
