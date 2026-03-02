<?php

declare(strict_types=1);

namespace App\Application\Auth\Results;

use App\Domain\Identity\Entities\UserEntity;
use DateTimeImmutable;

/**
 * VerifyOtpResult - Pure Application DTO
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: Uses native DateTimeImmutable, no Carbon dependency.             ║
 * ║  IMMUTABLE: final readonly ensures state consistency.                   ║
 * ║  TYPED: Primitive transport only — no Domain Entity leakage.            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class VerifyOtpResult
{
    public function __construct(
        public ?string $userId,
        public ?string $tenantId,
        public DateTimeImmutable $verifiedAt,
        public bool $requiresTwoFactor = false,
        public bool $requiresRegistration = false,
        public ?string $identifier = null,
    ) {}

    public static function forUser(UserEntity $user): self
    {
        return new self(
            userId: $user->id(),
            tenantId: $user->tenantId(),
            verifiedAt: new DateTimeImmutable,
        );
    }

    public static function forNewUser(string $identifier): self
    {
        return new self(
            userId: null,
            tenantId: null,
            verifiedAt: new DateTimeImmutable,
            requiresRegistration: true,
            identifier: $identifier,
        );
    }

    public function hasUser(): bool
    {
        return $this->userId !== null;
    }
}
