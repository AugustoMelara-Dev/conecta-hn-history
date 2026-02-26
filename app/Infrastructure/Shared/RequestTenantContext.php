<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\TenantContext;
use Illuminate\Http\Request;

/**
 * RequestTenantContext - Infrastructure Implementation
 *
 * RESPONSIBILITY:
 * Resolve Tenant ID from the current HTTP Request/User.
 */
final class RequestTenantContext implements TenantContext
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getTenantUuid(): string
    {
        $user = $this->request->user();

        if (! $user) {
            throw new \RuntimeException('Tenant Context Error: User not authenticated.');
        }

        $tenantUuid = $user->tenant_uuid ?? ($user->tenant->uuid ?? null);

        if (! $tenantUuid) {
            throw new \RuntimeException('Tenant Context Error: User has no associated tenant UUID.');
        }

        return (string) $tenantUuid;
    }

    /**
     * {@inheritDoc}
     */
    public function getTenantId(): int
    {
        // Try resolving from authenticated user
        // We use dynamic property access to avoid hard import of User model class
        // but strictly check for existence.

        $user = $this->request->user();

        if (! $user) {
            throw new \RuntimeException('Tenant Context Error: User not authenticated.');
        }

        $tenantId = $user->tenant_id ?? null;

        if (! $tenantId) {
            throw new \RuntimeException('Tenant Context Error: User has no associated tenant.');
        }

        return (int) $tenantId;
    }

    /**
     * {@inheritDoc}
     */
    public function getTenantData(?array $only = null): array
    {
        $user = $this->request->user();

        if (! $user || ! $user->tenant) {
            throw new \RuntimeException('Tenant Context Error: Tenant not available.');
        }

        $tenant = $user->tenant;

        // If $only is specified, filter to those keys only
        if ($only !== null) {
            return $tenant->only($only);
        }

        // Default: return essential public fields
        return $tenant->only(['id', 'name', 'slug', 'is_pro']);
    }
}
