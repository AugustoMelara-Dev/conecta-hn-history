<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

/**
 * OtpHasherInterface - Cryptographic Abstraction
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ALGORITHM AGNOSTIC: Hides HMAC/Bcrypt/Argon2 details.                  ║
 * ║  INVERSION OF CONTROL: Handlers depend on this contract, not implementation. ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
interface OtpHasherInterface
{
    /**
     * Compute hash for a plain OTP code.
     */
    public function hash(string $plainOtp): string;

    /**
     * Verify if a plain OTP matches the hashed value.
     * Must be implemented using constant-time comparison.
     */
    public function verify(string $plainOtp, string $hashedOtp): bool;
}
