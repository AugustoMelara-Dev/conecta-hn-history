<?php

declare(strict_types=1);

namespace App\Application\Auth\Results;

use App\Application\Auth\Enums\AuthStatus;
use App\Domain\Identity\Entities\UserEntity;

/**
 * SocialLoginResult - Pure Application Output
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PROTOCOL AGNOSTIC: Zero knowledge of HTTP, URLs, or Redirects.         ║
 * ║  STATE FOCUSED: Exposes the exact result state via AuthStatus Enum.     ║
 * ║  INMUTABLE: Final readonly DTO. Transports primitives only.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SocialLoginResult
{
    public function __construct(
        public AuthStatus $status,
        public string $userId,
        public ?string $tenantId = null,
        public ?string $token = null
    ) {}

    public static function success(UserEntity $user): self
    {
        $status = $user->tenantId() === null
            ? AuthStatus::REQUIRES_ONBOARDING
            : AuthStatus::SUCCESS;

        return new self($status, $user->id(), $user->tenantId());
    }

    public static function newUser(UserEntity $user): self
    {
        return new self(AuthStatus::NEW_USER, $user->id(), $user->tenantId());
    }
}
