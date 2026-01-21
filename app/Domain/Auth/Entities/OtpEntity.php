<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entities;

use DateTimeImmutable;

/**
 * OtpEntity - Tenant-Aware Domain Object
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  MULTI-TENANT: Includes tenantId for complete context in memory.        ║
 * ║  POPO: Plain Old PHP Object. Zero Framework Coupling.                   ║
 * ║  IMMUTABLE: Readonly state ensures predictability.                      ║
 * ║  NATIVE: Uses DateTimeImmutable for time, strict scalar types.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class OtpEntity
{
    public function __construct(
        public string $id,
        public ?int $userId,
        public string $identifier,
        public string $tenantId,
        public string $hashedCode,
        public string $type,
        public DateTimeImmutable $expiresAt,
        public int $attempts,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * Check if OTP has expired relative to 'now'.
     */
    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt < $now;
    }

    /**
     * Check if maximum verification attempts have been exceeded.
     *
     * @param  int  $maxAttempts  Configurable max attempts (injected policy)
     */
    public function maxAttemptsExceeded(int $maxAttempts = 5): bool
    {
        return $this->attempts >= $maxAttempts;
    }
}
