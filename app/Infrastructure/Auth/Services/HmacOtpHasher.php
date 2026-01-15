<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Services;

use App\Domain\Auth\Services\OtpHasherInterface;
use RuntimeException;

/**
 * HmacOtpHasher - Solid State Cryptography Adapter
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FAIL-FAST: Validates app key presence on instantiation.                ║
 * ║  PURE INJECTION: Config dependencies injected via constructor.          ║
 * ║  TIMING-SAFE: Uses hash_equals for resistant comparison.                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class HmacOtpHasher implements OtpHasherInterface
{
    public function __construct(private string $appSecret)
    {
        if (empty($this->appSecret)) {
            throw new RuntimeException('FATAL: Application key (app.key) is missing. Cannot initialize HMAC Hasher.');
        }
    }

    public function hash(string $plainOtp): string
    {
        return hash_hmac('sha256', $plainOtp, $this->appSecret);
    }

    public function verify(string $plainOtp, string $hashedOtp): bool
    {
        $calculatedHash = $this->hash($plainOtp);
        return hash_equals($hashedOtp, $calculatedHash);
    }
}
