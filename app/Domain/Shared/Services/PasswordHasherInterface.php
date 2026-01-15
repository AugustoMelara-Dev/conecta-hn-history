<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

/**
 * PasswordHasherInterface - Cryptographic Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEPENDENCY INVERSION: Decouples Domain from hashing implementation.    ║
 * ║  SECURITY: Enforces verify/hash contract for password management.       ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface PasswordHasherInterface
{
    /**
     * Compute hash for a plain password string (e.g., Bcrypt, Argon2).
     */
    public function hash(string $plain): string;

    /**
     * Verify if a plain password matches the hashed value.
     */
    public function verify(string $plain, string $hash): bool;
}
