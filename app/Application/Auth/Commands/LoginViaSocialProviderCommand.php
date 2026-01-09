<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

/**
 * LoginViaSocialProviderCommand - Provider-Agnostic Social Identity Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  SOCIALITE AGNOSTIC: No Laravel\Socialite references - normalized data. ║
 * ║  UNIVERSAL: Works for Google, GitHub, LinkedIn, Apple, etc.             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderCommand
{
    public function __construct(
        /** Provider slug (google, github, linkedin, apple) */
        public string $providerName,
        
        /** Provider's unique user ID (immutable) */
        public string $providerId,
        
        /** Verified email address */
        public string $email,
        
        /** Display name */
        public string $name,
        
        /** Avatar URL */
        public ?string $avatarUrl = null,
        
        /** OAuth access token (for API calls) */
        public ?string $accessToken = null,
        
        /** OAuth refresh token */
        public ?string $refreshToken = null,
    ) {}
}
