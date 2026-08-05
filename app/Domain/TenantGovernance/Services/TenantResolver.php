<?php

declare(strict_types=1);

namespace App\Domain\TenantGovernance\Services;

use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;

/**
 * TenantResolver - Secure Tenant Discovery Service
 *
 * SECURITY: Central point for resolving tenants from untrusted input.
 * Uses cache to prevent database hammering from malicious requests.
 *
 * VALIDATION LAYERS:
 * 1. is_active = true (admin approval)
 * 2. subscription_status in ['active', 'trial'] (no morosos)
 */
final class TenantResolver
{
    public function __construct(
        private readonly TenantRepositoryInterface $repository
    ) {}

    /**
     * Find a PUBLIC tenant by ID (for favorites, follows, etc).
     *
     * SECURITY: Only returns active, publicly visible tenants.
     * Returns null for inactive or non-existent tenants (no information leak).
     */
    public function findPublicById(string $tenantId): ?object
    {
        // Cache responsibility delegated to Infrastructure (Repository Decorator)
        $tenant = $this->repository->findById((string) $tenantId);

        if (! $tenant) {
            return null;
        }

        // Domain logic: validate public availability through entity methods.

        if ($tenant->isActive() && in_array($tenant->subscriptionStatus(), ['active', 'trial'], true)) {
            return $tenant;
        }

        return null;
    }

    /**
     * Find a PUBLIC tenant by slug.
     */
    public function findPublicBySlug(string $slug): ?object
    {
        // Cache responsibility delegated to Infrastructure
        $tenant = $this->repository->findBySlug($slug);

        if (! $tenant) {
            return null;
        }

        if ($tenant->isActive() && in_array($tenant->subscriptionStatus(), ['active', 'trial'], true)) {
            return $tenant;
        }

        return null;
    }

    /**
     * Find a PUBLIC tenant by domain.
     */
    public function findPublicByDomain(string $domain): ?object
    {
        $tenant = $this->repository->findByDomain($domain);

        if (! $tenant) {
            return null;
        }

        if ($tenant->isActive() && in_array($tenant->subscriptionStatus(), ['active', 'trial'], true)) {
            return $tenant;
        }

        return null;
    }

    /**
     * Verify a tenant exists and is public.
     */
    public function isPublic(string $tenantId): bool
    {
        return $this->findPublicById($tenantId) !== null;
    }
}
