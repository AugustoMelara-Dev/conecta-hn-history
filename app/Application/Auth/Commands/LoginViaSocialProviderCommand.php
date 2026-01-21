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
final readonly class LoginViaSocialProviderCommand implements \App\Application\Common\Contracts\HashableCommand
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
        public ?string $avatarUrl,

        /** OAuth access token (for API calls) */
        public ?string $accessToken,

        /** OAuth refresh token */
        public ?string $refreshToken,

        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey,
    ) {}

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCommandHash(): string
    {
        return hash('sha256', json_encode([
            'tenantId' => $this->tenantId,
            'providerName' => $this->providerName->value,
            'providerId' => $this->providerId,
            'email' => $this->email, // Hashing email prevents spoofing if providerId collides (unlikely but safe)
        ]));
    }
}
