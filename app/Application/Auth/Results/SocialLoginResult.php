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
 * ║  INMUTABLE: Final readonly DTO.                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SocialLoginResult
{
    public function __construct(
        public AuthStatus $status,
        public UserEntity $user,
        public ?string $token = null // Optional JWT/Sanctum token if needed immediately
    ) {}

    public static function success(UserEntity $user): self
    {
        // If user has no tenant, they require onboarding
        $status = $user->tenantId === null
            ? AuthStatus::REQUIRES_ONBOARDING
            : AuthStatus::SUCCESS;

        return new self($status, $user);
    }

    public static function newUser(UserEntity $user): self
    {
        return new self(AuthStatus::NEW_USER, $user);
    }
}
