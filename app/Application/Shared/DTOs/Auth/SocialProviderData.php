<?php

declare(strict_types=1);

namespace App\Application\Shared\DTOs\Auth;

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

}
