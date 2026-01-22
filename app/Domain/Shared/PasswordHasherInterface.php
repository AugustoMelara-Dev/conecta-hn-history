<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Interface PasswordHasherInterface
 *
 * Decouples domain logic from hashing implementation (Bcrypt/Argon2).
 * Enforces security by design.
 */
interface PasswordHasherInterface
{
    /**
     * Hash the given plain text.
     */
    public function hash(string $plainText): string;

    /**
     * Verify that the given plain text matches the hash.
     */
    public function verify(string $plainText, string $hash): bool;
}
