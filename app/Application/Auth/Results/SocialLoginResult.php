<?php

declare(strict_types=1);

namespace App\Application\Auth\Results;

/**
 * SocialLoginResult - Immutable Output Contract for Social Authentication
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  UX CRITICAL: isNewUser flag distinguishes onboarding vs dashboard.     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SocialLoginResult
{
    public function __construct(
        public int $userId,
        public int $tenantId,
        public bool $isNewUser,
        public string $redirectUrl,
    ) {}

    /**
     * Factory for existing user login.
     */
    public static function forExistingUser(object $user): self
    {
        return new self(
            userId: $user->id,
            tenantId: $user->tenant_id ?? 0,
            isNewUser: false,
            redirectUrl: url('/dashboard'),
        );
    }

    /**
     * Factory for newly registered user.
     */
    public static function forNewUser(object $user, object $tenant): self
    {
        return new self(
            userId: $user->id,
            tenantId: $tenant->id,
            isNewUser: true,
            redirectUrl: url("/tenant/{$tenant->slug}/onboarding"),
        );
    }
}
