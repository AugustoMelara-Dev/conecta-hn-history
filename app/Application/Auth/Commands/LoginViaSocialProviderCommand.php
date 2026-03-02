<?php

declare(strict_types=1);

namespace App\Application\Auth\Commands;

use App\Domain\Shared\Enums\ProviderName;

/**
 * LoginViaSocialProviderCommand - Provider-Agnostic Social Identity Contract
 *
 * â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
 * â•‘  TENANT AWARE: Mandatory tenantId to prevent cross-tenant account hijackâ•‘
 * â•‘  SOCIALITE AGNOSTIC: No Laravel\Socialite references - normalized data. â•‘
 * â•‘  UNIVERSAL: Works for Google, GitHub, LinkedIn, Apple, etc.             â•‘
 * â•‘  TYPE SAFETY: Enforced via ProviderName Enum.                           â•‘
 * â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
 */
final readonly class LoginViaSocialProviderCommand implements \App\Application\Shared\Contracts\HashableCommandInterface, \App\Application\Shared\Contracts\SensitiveDataInterface
{
    public function __construct(
        /** Mandatory Tenant Context (Axiom A) - Social login is always INTO a specific tenant */
        public string $tenantId,

        /** Provider enum (google, github, linkedin, apple) */
        public ProviderName $providerName,

        /** Provider's unique user ID (immutable) */
        public string $providerId,

        /** Verified email address */
        #[\SensitiveParameter] public string $email,

        /** Display name */
        #[\SensitiveParameter] public string $name,

        /** Avatar URL */
        public ?string $avatarUrl,

        /** OAuth access token (for API calls) */
        #[\SensitiveParameter] public ?string $accessToken,

        /** OAuth refresh token */
        #[\SensitiveParameter] public ?string $refreshToken,

        /** Unique Receipt ID (UUID v7) for transactional idempotency */
        public string $idempotencyKey,
        public int $schemaVersion = 1,
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

    public function getSensitiveProperties(): array
    {
        return ['accessToken', 'refreshToken'];
    }
}
