<?php

declare(strict_types=1);

namespace App\Application\DTOs\Auth;

use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * SocialProviderData - Immutable Data Transfer Object
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DTO Pattern: Data Carrier                                              ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  Normalization of Socialite data into a strict contract.                 ║
 * ║  Properties are public readonly for immutable access.                    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class SocialProviderData
{
    public function __construct(
        public string $name,
        #[\SensitiveParameter] public string $email,
        public ?string $avatar,
        public string $provider_id,
        public string $provider_name,
        public ?string $token
    ) {}

    /**
     * Factory method to create DTO from Socialite User.
     */
    public static function fromSocialite(string $provider, SocialiteUser $socialiteUser): self
    {
        return new self(
            name: $socialiteUser->getName() ?? explode('@', $socialiteUser->getEmail())[0],
            email: $socialiteUser->getEmail(),
            avatar: $socialiteUser->getAvatar(),
            provider_id: $socialiteUser->getId(),
            provider_name: strtolower($provider),
            token: $socialiteUser->token ?? null
        );
    }
}
