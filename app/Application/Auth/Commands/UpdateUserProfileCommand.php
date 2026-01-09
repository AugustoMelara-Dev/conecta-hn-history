<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * UpdateUserProfileCommand - Immutable Input Contract for Profile Update
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  EMAIL CHANGE: If email changes, verification is reset automatically.   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UpdateUserProfileCommand
{
    public function __construct(
        /** User ID to update */
        public int $userId,
        
        /** New display name */
        public string $name,
        
        /** New email address */
        public string $email,
    ) {}
}
