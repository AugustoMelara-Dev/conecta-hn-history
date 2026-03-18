<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

/**
 * RegisterTenantResult - Immutable Output Contract for Tenant Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ONBOARDING FLOW: Provides all data needed for post-registration UX.    ║
 * ║  IDEMPOTENCY READY: Implements strict serialization for caching.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RegisterTenantResult
{
    public function __construct(
        public string $tenantId,
        public string $domain,
        public string $ownerEmail,
        public string $setupUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'domain' => $this->domain,
            'ownerEmail' => $this->ownerEmail,
            'setupUrl' => $this->setupUrl,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: (string) $data['tenantId'],
            domain: (string) $data['domain'],
            ownerEmail: (string) $data['ownerEmail'],
            setupUrl: (string) $data['setupUrl'],
        );
    }

    /**
     * Factory method.
     */
    public static function fromEntities(
        object $tenant,
        object $user,
        string $appUrl
    ): self {
        // Domain construction logic
        $domain = $tenant->domain().'.'.parse_url($appUrl, PHP_URL_HOST);
        $setupUrl = rtrim($appUrl, '/')."/tenant/{$tenant->domain()}/setup";

        return new self(
            tenantId: $tenant->id(),
            domain: $domain,
            ownerEmail: $user->email(),
            setupUrl: $setupUrl,
        );
    }
}
