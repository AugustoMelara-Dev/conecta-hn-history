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
        ?string $appUrl = null
    ): self {
        $tenantDomain = method_exists($tenant, 'domain') ? (string) $tenant->domain() : (string) ($tenant->slug ?? '');
        $tenantId = method_exists($tenant, 'id') ? (string) $tenant->id() : (string) ($tenant->id ?? '');
        $ownerEmail = method_exists($user, 'email') ? (string) $user->email() : (string) ($user->email ?? '');
        $baseUrl = rtrim((string) ($appUrl ?? config('app.url')), '/');
        $setupUrl = "{$baseUrl}/app/{$tenantDomain}";

        return new self(
            tenantId: $tenantId,
            domain: $tenantDomain,
            ownerEmail: $ownerEmail,
            setupUrl: $setupUrl,
        );
    }
}
