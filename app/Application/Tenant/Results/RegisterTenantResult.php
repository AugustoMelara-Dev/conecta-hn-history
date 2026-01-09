<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

/**
 * RegisterTenantResult - Immutable Output Contract for Tenant Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ONBOARDING FLOW: Provides all data needed for post-registration UX.    ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class RegisterTenantResult
{
    public function __construct(
        public int $tenantId,
        public string $domain,
        public string $ownerEmail,
        public string $setupUrl,
    ) {}

    /**
     * Factory method.
     */
    public static function fromEntities(
        object $tenant,
        object $user,
        ?string $appUrl = null
    ): self {
        // Domain construction logic (placeholder, adjust based on config)
        $domain = $tenant->slug . '.' . parse_url($appUrl ?? config('app.url'), PHP_URL_HOST);
        $setupUrl = ($appUrl ?? config('app.url')) . "/tenant/{$tenant->slug}/setup";

        return new self(
            tenantId: $tenant->id,
            domain: $domain,
            ownerEmail: $user->email,
            setupUrl: $setupUrl,
        );
    }
}
