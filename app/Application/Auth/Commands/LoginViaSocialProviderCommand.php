<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

use App\Domain\Shared\Enums\ProviderName;

/**
 * LoginViaSocialProviderCommand - Provider-Agnostic Social Identity Contract
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  TENANT AWARE: Mandatory tenantId to prevent cross-tenant account hijack║
 * ║  SOCIALITE AGNOSTIC: No Laravel\Socialite references - normalized data. ║
 * ║  UNIVERSAL: Works for Google, GitHub, LinkedIn, Apple, etc.             ║
 * ║  TYPE SAFETY: Enforced via ProviderName Enum.                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class LoginViaSocialProviderCommand
{
    public function __construct(
        /** Mandatory Tenant Context (Axiom A) - Social login is always INTO a specific tenant */
        public string $tenantId,

        /** Provider enum (google, github, linkedin, apple) */
        public ProviderName $providerName,
        
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
