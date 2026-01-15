<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entities;

use DateTimeImmutable;

/**
 * OtpEntity - Pure Domain Object
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  POPO: Plain Old PHP Object. Zero Framework Coupling.                   ║
 * ║  IMMUTABLE: Readonly state ensures predictability.                      ║
 * ║  NATIVE: Uses DateTimeImmutable for time, strict scalar types.          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class OtpEntity
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        public string $id,
        public ?int $userId,
        public string $identifier,
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
     */
    public function maxAttemptsExceeded(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }
}
