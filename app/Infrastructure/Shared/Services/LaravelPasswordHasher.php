<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared\Services;

use App\Domain\Shared\Services\PasswordHasherInterface;
use Illuminate\Support\Facades\Hash;

/**
 * LaravelPasswordHasher - Infrastructure Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ADAPTER PATTERN: Translates Domain Interface -> Framework Implementation.║
 * ║  SINGLE RESPONSIBILITY: Only delegated hashing logic. No business rules.║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class LaravelPasswordHasher implements PasswordHasherInterface
{
    /**
     * Compute hash using the framework's configured driver (Bcrypt/Argon).
     */
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    /**
     * Verify plain password against hash using framework's secure comparison.
     */
    public function verify(string $plain, string $hash): bool
    {
        return Hash::check($plain, $hash);
    }
}
