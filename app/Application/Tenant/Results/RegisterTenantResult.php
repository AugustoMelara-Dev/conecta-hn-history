<?php

declare(strict_types=1);

namespace App\Application\Tenant\Results;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * RegisterTenantResult - Immutable Output Contract for Tenant Registration
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ONBOARDING FLOW: Provides all data needed for post-registration UX.    ║
 * ║  IDEMPOTENCY READY: Implements strict serialization for caching.        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
#[TypeScript]
final readonly class RegisterTenantResult
{
    public function __construct(
        public int $tenantId,
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
            tenantId: (int) $data['tenantId'],
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
        ?string $appUrl = null
    ): self {
        // Domain construction logic
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
